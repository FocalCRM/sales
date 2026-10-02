<?php

declare(strict_types=1);

use Focal\Core\Actions\MergeContactsAction;
use Focal\Core\Models\Contact;
use Focal\Sales\Models\SalesMeetingBooking;
use Focal\Sales\Models\SalesMeetingLink;
use Focal\Sales\Models\SalesSequence;
use Focal\Sales\Models\SalesSequenceEnrollment;
use Focal\Sales\Tests\Fixtures\User;

function mergeTestSequence(string $name = 'Outbound'): SalesSequence
{
    return SalesSequence::query()->create([
        'name' => $name,
        'is_active' => true,
        'steps' => [
            ['step' => 1, 'type' => 'email', 'delay_days' => 0, 'title' => 'Intro'],
            ['step' => 2, 'type' => 'call', 'delay_days' => 2, 'title' => 'Call'],
            ['step' => 3, 'type' => 'email', 'delay_days' => 3, 'title' => 'Breakup'],
        ],
    ]);
}

function mergeTestEnroll(Contact $contact, SalesSequence $sequence, string $status = 'active', int $step = 1): SalesSequenceEnrollment
{
    return SalesSequenceEnrollment::query()->create([
        'sequence_id' => $sequence->id,
        'contact_id' => $contact->id,
        'current_step' => $step,
        'status' => $status,
        'next_step_due_at' => $status === 'active' ? now()->toDateString() : null,
    ]);
}

test('merging contacts moves sequence enrollments to the primary', function () {
    [$primary, $secondary] = Contact::factory()->count(2)->create();
    $enrollment = mergeTestEnroll($secondary, mergeTestSequence());

    app(MergeContactsAction::class)->execute($primary, $secondary);

    expect($enrollment->fresh()->contact_id)->toBe($primary->id)
        ->and(SalesSequenceEnrollment::query()->where('contact_id', $secondary->id)->count())->toBe(0);
});

test('merging contacts keeps one enrollment per sequence, preferring the further-along active one', function () {
    [$primary, $secondary] = Contact::factory()->count(2)->create();
    $sequence = mergeTestSequence();
    $primaryEnrollment = mergeTestEnroll($primary, $sequence, step: 1);
    $secondaryEnrollment = mergeTestEnroll($secondary, $sequence, step: 2);

    app(MergeContactsAction::class)->execute($primary, $secondary);

    expect(SalesSequenceEnrollment::query()->where('sequence_id', $sequence->id)->pluck('id')->all())->toBe([$secondaryEnrollment->id])
        ->and($secondaryEnrollment->fresh()->contact_id)->toBe($primary->id)
        ->and($primaryEnrollment->fresh())->toBeNull();
});

test('merging contacts never restarts a sequence the other record finished or was unenrolled from', function () {
    [$primary, $secondary] = Contact::factory()->count(2)->create();
    $sequence = mergeTestSequence();
    mergeTestEnroll($primary, $sequence, step: 3);
    $stopped = mergeTestEnroll($secondary, $sequence, status: 'unenrolled');

    app(MergeContactsAction::class)->execute($primary, $secondary);

    $remaining = SalesSequenceEnrollment::query()->where('sequence_id', $sequence->id)->sole();

    expect($remaining->id)->toBe($stopped->id)
        ->and($remaining->contact_id)->toBe($primary->id)
        ->and($remaining->status)->toBe('unenrolled');
});

test('merging contacts keeps enrollments in different sequences', function () {
    [$primary, $secondary] = Contact::factory()->count(2)->create();
    mergeTestEnroll($primary, mergeTestSequence('A'));
    mergeTestEnroll($secondary, mergeTestSequence('B'));

    app(MergeContactsAction::class)->execute($primary, $secondary);

    expect(SalesSequenceEnrollment::query()->where('contact_id', $primary->id)->count())->toBe(2);
});

test('merging contacts moves meeting bookings to the primary', function () {
    [$primary, $secondary] = Contact::factory()->count(2)->create();
    $user = User::factory()->create();
    $link = SalesMeetingLink::create([
        'slug' => 'demo',
        'title' => 'Demo',
        'duration_minutes' => 30,
        'working_hours' => [],
        'user_id' => $user->id,
        'is_active' => true,
    ]);
    $booking = SalesMeetingBooking::create([
        'uid' => (string) str()->uuid(),
        'meeting_link_id' => $link->id,
        'user_id' => $user->id,
        'contact_id' => $secondary->id,
        'invitee_name' => 'Dana',
        'invitee_email' => $secondary->email,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(30),
        'timezone' => 'UTC',
    ]);

    app(MergeContactsAction::class)->execute($primary, $secondary);

    expect($booking->fresh()->contact_id)->toBe($primary->id);
});
