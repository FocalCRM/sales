<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Core\Models\Contact;
use Focal\Sales\Actions\EnrollContactInSequenceAction;
use Focal\Sales\Models\SalesSequence;
use Focal\Sales\Models\SalesSequenceEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_sequence_and_enroll_contact(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Alex',
            'last_name' => 'Vance',
            'email' => 'alex@blackmesa.internal',
        ]);

        $sequence = SalesSequence::query()->create([
            'name' => 'Enterprise Outbound Cadence',
            'description' => 'Multi-channel 3-step sequence',
            'is_active' => true,
            'steps' => [
                ['step' => 1, 'type' => 'email', 'delay_days' => 0, 'title' => 'Introductory Cold Email'],
                ['step' => 2, 'type' => 'call', 'delay_days' => 2, 'title' => 'Follow-up Cold Call'],
                ['step' => 3, 'type' => 'linkedin', 'delay_days' => 3, 'title' => 'LinkedIn Connection Request'],
            ],
        ]);

        $this->assertSame(3, $sequence->totalSteps());

        $enrollAction = app(EnrollContactInSequenceAction::class);
        $enrollment = $enrollAction->execute($contact, $sequence);

        $this->assertInstanceOf(SalesSequenceEnrollment::class, $enrollment);
        $this->assertSame(1, $enrollment->current_step);
        $this->assertSame('active', $enrollment->status);
        $this->assertNotNull($enrollment->next_step_due_at);

        // Advance to step 2
        $enrollment->advanceStep();
        $enrollment->refresh();

        $this->assertSame(2, $enrollment->current_step);
        $this->assertSame('active', $enrollment->status);

        // Advance to step 3
        $enrollment->advanceStep();
        $enrollment->refresh();

        $this->assertSame(3, $enrollment->current_step);
        $this->assertSame('active', $enrollment->status);

        // Advance past final step marks status completed
        $enrollment->advanceStep();
        $enrollment->refresh();

        $this->assertSame('completed', $enrollment->status);
        $this->assertNull($enrollment->next_step_due_at);
    }
}
