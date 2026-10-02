<?php

declare(strict_types=1);

namespace Focal\Sales\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Focal\Core\Enums\ActivityStatus;
use Focal\Core\Enums\ActivityType;
use Focal\Core\Enums\LeadStatus;
use Focal\Core\Models\Activity;
use Focal\Core\Models\Contact;
use Focal\Core\Support\ContactLookup;
use Focal\Sales\Exceptions\MeetingSlotUnavailableException;
use Focal\Sales\Mail\MeetingBookedMail;
use Focal\Sales\Mail\SalesMail;
use Focal\Sales\Models\SalesMeetingBooking;
use Focal\Sales\Models\SalesMeetingLink;
use Focal\Sales\Models\SalesSequenceEnrollment;
use Focal\Sales\Services\MeetingAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookMeetingAction
{
    public function __construct(
        protected MeetingAvailability $availability,
    ) {}

    /**
     * Book a meeting on a rep's calendar link: re-check the slot, record the booking, create or match
     * the Contact, log the meeting activity, and queue confirmations (with an .ics invite) to both sides.
     *
     * @return array{contact: Contact, activity: Activity, booking: SalesMeetingBooking}
     *
     * @throws MeetingSlotUnavailableException When the time is not an open slot on the link.
     */
    public function execute(
        SalesMeetingLink $link,
        string $fullName,
        string $email,
        CarbonInterface $scheduledAt,
        ?string $phone = null,
        ?string $notes = null
    ): array {
        $email = ContactLookup::normalizeEmail($email);

        return DB::transaction(
            fn (): array => $this->bookUnderLock($link, $fullName, $email, $scheduledAt, $phone, $notes)
        );
    }

    /**
     * Runs inside the booking transaction: serialize bookings per host by locking the host's links,
     * then re-check the slot under the lock before booking.
     *
     * @return array{contact: Contact, activity: Activity, booking: SalesMeetingBooking}
     *
     * @throws MeetingSlotUnavailableException When the time is not an open slot on the link.
     */
    protected function bookUnderLock(
        SalesMeetingLink $link,
        string $fullName,
        string $email,
        CarbonInterface $scheduledAt,
        ?string $phone,
        ?string $notes
    ): array {
        SalesMeetingLink::query()->where('user_id', $link->user_id)->lockForUpdate()->get(['id']);

        if (! $this->availability->isAvailable($link, $scheduledAt)) {
            throw MeetingSlotUnavailableException::at($scheduledAt);
        }

        return $this->book($link, $fullName, $email, $scheduledAt, $phone, $notes);
    }

    /**
     * @return array{contact: Contact, activity: Activity, booking: SalesMeetingBooking}
     */
    protected function book(
        SalesMeetingLink $link,
        string $fullName,
        string $email,
        CarbonInterface $scheduledAt,
        ?string $phone,
        ?string $notes
    ): array {
        // Datetime columns are written as-is, so store times in the app's timezone.
        $startsAt = CarbonImmutable::instance($scheduledAt)->setTimezone((string) config('app.timezone', 'UTC'));

        // Same lookup as the Service channels: case and surrounding whitespace are ignored, so a
        // contact saved as "Dana@Example.com" is reused rather than duplicated.
        $contact = ContactLookup::findByEmail($email);

        $parts = preg_split('/\s+/', trim($fullName));
        $partsArray = is_array($parts) ? $parts : [$fullName];
        $firstName = $partsArray[0] ?? $fullName;
        $lastName = count($partsArray) > 1 ? implode(' ', array_slice($partsArray, 1)) : '';

        if ($contact === null) {
            /** @var Contact $contact */
            $contact = Contact::query()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => ContactLookup::normalizeEmail($email),
                'phone' => $phone,
                'lead_status' => LeadStatus::Open,
                'owner_id' => $link->user_id,
            ]);
        } else {
            if ($phone !== null && ! $contact->phone) {
                $contact->updateQuietly(['phone' => $phone]);
            }
        }

        /** @var Activity $activity */
        $activity = $contact->logActivity(
            type: ActivityType::Meeting,
            title: "{$link->title} with {$contact->full_name}",
            body: $notes !== null && $notes !== '' ? $notes : "Booked online via /meet/{$link->slug}",
            metadata: [
                'duration_minutes' => $link->duration_minutes,
                'booking_slug' => $link->slug,
            ],
            status: ActivityStatus::Pending,
            dueAt: $startsAt,
            creatorId: $link->user_id
        );

        // Auto-unenroll contact from active outbound sales cadences upon meeting booking
        SalesSequenceEnrollment::query()
            ->where('contact_id', $contact->id)
            ->where('status', 'active')
            ->update([
                'status' => 'unenrolled',
                'next_step_due_at' => null,
            ]);

        /** @var SalesMeetingBooking $booking */
        $booking = SalesMeetingBooking::query()->create([
            'uid' => (string) Str::uuid(),
            'meeting_link_id' => $link->id,
            'user_id' => $link->user_id,
            'contact_id' => $contact->id,
            'activity_id' => $activity->id,
            'invitee_name' => trim($fullName),
            'invitee_email' => $email,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($link->duration_minutes),
            'timezone' => $link->timezoneName(),
        ]);

        $booking->setRelation('meetingLink', $link);

        // Queued, and only dispatched once the booking transaction commits.
        SalesMail::to($email, $booking->invitee_name)->queue(new MeetingBookedMail($booking, 'invitee'));

        $hostEmail = $link->user->getAttribute('email');

        if (is_string($hostEmail) && $hostEmail !== '') {
            SalesMail::to($hostEmail)->queue(new MeetingBookedMail($booking, 'host'));
        }

        return [
            'contact' => $contact,
            'activity' => $activity,
            'booking' => $booking,
        ];
    }
}
