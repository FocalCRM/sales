<?php

declare(strict_types=1);

namespace Focal\Sales\Actions;

use Carbon\CarbonInterface;
use Focal\Core\Enums\ActivityStatus;
use Focal\Core\Enums\ActivityType;
use Focal\Core\Enums\LeadStatus;
use Focal\Core\Models\Activity;
use Focal\Core\Models\Contact;
use Focal\Sales\Models\SalesMeetingLink;
use Focal\Sales\Models\SalesSequenceEnrollment;

class BookMeetingAction
{
    /**
     * Book a meeting on a rep's calendar link, creating or matching the Contact and queuing the meeting activity.
     *
     * @return array{contact: Contact, activity: Activity}
     */
    public function execute(
        SalesMeetingLink $link,
        string $fullName,
        string $email,
        CarbonInterface $scheduledAt,
        ?string $phone = null,
        ?string $notes = null
    ): array {
        $email = strtolower(trim($email));

        /** @var Contact|null $contact */
        $contact = Contact::query()->whereEmail($email)->first();

        $parts = preg_split('/\s+/', trim($fullName));
        $partsArray = is_array($parts) ? $parts : [$fullName];
        $firstName = $partsArray[0] ?? $fullName;
        $lastName = count($partsArray) > 1 ? implode(' ', array_slice($partsArray, 1)) : '';

        if ($contact === null) {
            /** @var Contact $contact */
            $contact = Contact::query()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
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
            dueAt: $scheduledAt,
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

        return [
            'contact' => $contact,
            'activity' => $activity,
        ];
    }
}
