<?php

declare(strict_types=1);

namespace Odden\Sales\Tests;

use Odden\Core\Enums\ActivityType;
use Odden\Core\Enums\LeadStatus;
use Odden\Core\Models\Contact;
use Odden\Sales\Actions\EnrollContactInSequenceAction;
use Odden\Sales\Actions\ProcessCadencesAction;
use Odden\Sales\Models\SalesEmailTemplate;
use Odden\Sales\Models\SalesSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProcessCadencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_process_due_cadences_and_dispatch_emails(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Gordon',
            'last_name' => 'Freeman',
            'email' => 'gordon@blackmesa.internal',
            'lead_status' => LeadStatus::New,
        ]);

        $template = SalesEmailTemplate::query()->create([
            'name' => 'Cold Intro',
            'subject' => 'Hello {{ contact.first_name }}',
            'body_html' => '<p>Excited to connect with {{ contact.full_name }}</p>',
            'category' => 'prospecting',
        ]);

        $sequence = SalesSequence::query()->create([
            'name' => 'Inbound Fast Response',
            'is_active' => true,
            'steps' => [
                [
                    'step' => 1,
                    'type' => 'email',
                    'delay_days' => 0,
                    'title' => 'Initial Intro Email',
                    'template_id' => $template->id,
                ],
                [
                    'step' => 2,
                    'type' => 'call',
                    'delay_days' => 1,
                    'title' => 'Follow-up Call',
                ],
            ],
        ]);

        $enrollAction = app(EnrollContactInSequenceAction::class);
        $enrollment = $enrollAction->execute($contact, $sequence);

        $action = new ProcessCadencesAction;
        $stats = $action->execute();

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(1, $stats['emails_sent']);

        $enrollment->refresh();
        $this->assertSame(2, $enrollment->current_step);

        $contact->refresh();
        $this->assertSame(LeadStatus::InProgress, $contact->lead_status);
        $this->assertNotNull($contact->last_contacted_at);

        $this->assertDatabaseHas('odden_activities', [
            'subject_type' => $contact->getMorphClass(),
            'subject_id' => $contact->id,
            'type' => ActivityType::Email->value,
            'title' => 'Hello Gordon',
        ]);
    }

    public function test_process_cadences_command_runs_successfully(): void
    {
        $this->artisan('sales:process-cadences')
            ->assertSuccessful();
    }
}
