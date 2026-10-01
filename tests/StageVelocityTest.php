<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Sales\Actions\CalculateStageVelocityAction;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\Pipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageVelocityTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_stage_velocity_and_stale_deal_counts(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $stages = $pipeline->stages()->get();
        $leadStage = $stages[0];
        $leadStage->update(['rot_after_days' => 5]);

        // Deal 1: in lead stage for 10 days (stale)
        Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $leadStage->id,
            'created_at' => now()->subDays(10),
        ]);

        // Deal 2: in lead stage for 2 days (not stale)
        Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $leadStage->id,
            'created_at' => now()->subDays(2),
        ]);

        $action = new CalculateStageVelocityAction;
        $metrics = $action->execute($pipeline->id);

        $this->assertArrayHasKey('stages', $metrics);
        $this->assertArrayHasKey('average_sales_cycle_days', $metrics);
        $this->assertSame(1, $metrics['stages'][$leadStage->id]['stale_deal_count']);
    }
}
