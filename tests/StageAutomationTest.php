<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Core\Models\Contact;
use Focal\Sales\Enums\StageAutomationActionType;
use Focal\Sales\Exceptions\StageRequirementException;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\Pipeline;
use Focal\Sales\Models\StageAutomation;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StageAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_requirement_guard_blocks_transition_when_contact_missing(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $stages = $pipeline->stages()->get();
        $leadStage = $stages[0];
        $proposalStage = $stages[2];

        // Create automation rule requiring contact to enter proposalStage
        StageAutomation::create([
            'stage_id' => $proposalStage->id,
            'name' => 'Require Contact for Proposal',
            'action_type' => StageAutomationActionType::RequireAssociatedContact,
            'is_active' => true,
        ]);

        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $leadStage->id,
        ]);

        $this->expectException(StageRequirementException::class);
        $this->expectExceptionMessage("Stage [{$proposalStage->name}] requires at least one associated contact.");

        // Attempting to move without associated contact must throw exception and abort
        $deal->moveToStage($proposalStage);
    }

    public function test_stage_requirement_guard_passes_when_contact_is_associated(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $stages = $pipeline->stages()->get();
        $leadStage = $stages[0];
        $proposalStage = $stages[2];

        StageAutomation::create([
            'stage_id' => $proposalStage->id,
            'name' => 'Require Contact for Proposal',
            'action_type' => StageAutomationActionType::RequireAssociatedContact,
            'is_active' => true,
        ]);

        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $leadStage->id,
        ]);

        $contact = Contact::factory()->create();
        $deal->associateWith($contact);

        // Transition now succeeds
        $deal->moveToStage($proposalStage);

        $this->assertSame($proposalStage->id, $deal->fresh()->stage_id);
    }

    public function test_stage_automation_creates_follow_up_task_activity(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $stages = $pipeline->stages()->get();
        $leadStage = $stages[0];
        $negotiationStage = $stages[3];

        StageAutomation::create([
            'stage_id' => $negotiationStage->id,
            'name' => 'Contract Review Task',
            'action_type' => StageAutomationActionType::CreateTask,
            'action_payload' => [
                'task_subject' => 'Send master services agreement',
                'due_days' => 3,
            ],
            'is_active' => true,
        ]);

        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $leadStage->id,
        ]);

        $deal->moveToStage($negotiationStage);

        $this->assertSame($negotiationStage->id, $deal->fresh()->stage_id);
        $this->assertCount(1, $deal->activities);
        $this->assertSame('Send master services agreement', $deal->activities->first()->title);
    }
}
