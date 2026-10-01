<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Core\Enums\ActivityType;
use Focal\Sales\Actions\ExecuteSalesPlaybookAction;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\Pipeline;
use Focal\Sales\Models\SalesPlaybook;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SalesPlaybookTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_execute_bant_playbook_and_sync_properties(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->first()->id,
        ]);

        $playbook = SalesPlaybook::query()->create(SalesPlaybook::defaultBantPreset());

        $answers = [
            'budget_status' => 'Allocated & Approved',
            'authority_role' => 'Final Economic Buyer',
            'core_pain' => 'High manual churn in legacy billing software.',
            'target_timeline' => 'This Quarter (1-3 months)',
        ];

        $action = new ExecuteSalesPlaybookAction;
        $action->execute($deal, $playbook, $answers);

        $deal->refresh();
        $this->assertSame('Allocated & Approved', $deal->getProperty('qualification_budget'));
        $this->assertSame('Final Economic Buyer', $deal->getProperty('qualification_authority'));
        $this->assertSame('This Quarter (1-3 months)', $deal->getProperty('qualification_timeline'));

        $this->assertDatabaseHas('focal_activities', [
            'subject_type' => $deal->getMorphClass(),
            'subject_id' => $deal->id,
            'type' => ActivityType::Note->value,
            'title' => 'Playbook: BANT Qualification Playbook',
        ]);
    }
}
