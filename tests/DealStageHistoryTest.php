<?php

declare(strict_types=1);

namespace Odden\Sales\Tests;

use Odden\Sales\Models\Deal;
use Odden\Sales\Models\DealStageHistory;
use Odden\Sales\Models\Pipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DealStageHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_time_in_stage_is_recorded_as_positive_whole_seconds(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        [$first, $second, $third] = [$pipeline->stages[0], $pipeline->stages[1], $pipeline->stages[2]];
        $deal = Deal::factory()->create(['pipeline_id' => $pipeline->id, 'stage_id' => $first->id]);

        $this->travelTo(now()->startOfMinute());
        $deal->moveToStage($second);

        $this->travel(90)->minutes();
        $deal->moveToStage($third);

        $closed = DealStageHistory::query()->where('deal_id', $deal->id)->where('to_stage_id', $second->id)->firstOrFail();

        $this->assertSame(5400, $closed->duration_in_stage_seconds);
    }

    public function test_moving_deal_between_stages_tracks_duration_and_history(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $stages = $pipeline->stages;
        $discoveryStage = $stages[0];
        $qualificationStage = $stages[1];
        $proposalStage = $stages[2];

        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $discoveryStage->id,
        ]);

        // Move to qualification
        $deal->moveToStage($qualificationStage);

        $this->assertDatabaseHas('odden_deal_stage_history', [
            'deal_id' => $deal->id,
            'from_stage_id' => $discoveryStage->id,
            'to_stage_id' => $qualificationStage->id,
        ]);

        // Move to proposal
        $deal->moveToStage($proposalStage);

        $historyRecords = DealStageHistory::query()->where('deal_id', $deal->id)->orderBy('id')->get();

        $this->assertCount(2, $historyRecords);
        $this->assertSame($qualificationStage->id, $historyRecords[0]->to_stage_id);
        $this->assertNotNull($historyRecords[0]->exited_at);
        $this->assertNotNull($historyRecords[0]->duration_in_stage_seconds);

        $this->assertSame($proposalStage->id, $historyRecords[1]->to_stage_id);
        $this->assertNull($historyRecords[1]->exited_at);
    }
}
