<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Sales\Actions\CalculateQuotaAttainmentAction;
use Focal\Sales\Enums\DealStatus;
use Focal\Sales\Enums\QuotaPeriod;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\Pipeline;
use Focal\Sales\Models\SalesQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_quota_attainment_and_pipeline_coverage(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $stages = $pipeline->stages()->get();
        $wonStage = $stages->firstWhere('is_closed_won', true) ?? $stages->last();

        $userId = 42;

        $quota = SalesQuota::create([
            'user_id' => $userId,
            'pipeline_id' => $pipeline->id,
            'period_type' => QuotaPeriod::Monthly,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'target_amount' => 50000.00,
            'currency' => 'USD',
        ]);

        // Won deal: $30,000 closed within this month
        Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $wonStage->id,
            'owner_id' => $userId,
            'amount' => 30000.00,
            'status' => DealStatus::Won,
            'closed_at' => now()->startOfMonth()->addDays(5),
        ]);

        // Open deal: $40,000 expected to close this month
        Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stages[1]->id,
            'owner_id' => $userId,
            'amount' => 40000.00,
            'status' => DealStatus::Open,
            'expected_close_date' => now()->startOfMonth()->addDays(15),
        ]);

        $action = new CalculateQuotaAttainmentAction;
        $metrics = $action->execute($quota);

        $this->assertEquals(50000.00, $metrics['target_amount']);
        $this->assertEquals(30000.00, $metrics['won_amount']);
        $this->assertEquals(60.0, $metrics['attainment_percent']); // 30,000 / 50,000 = 60%
        $this->assertEquals(20000.00, $metrics['gap_to_target']);
        $this->assertEquals(40000.00, $metrics['open_pipeline_amount']);
        $this->assertEquals(1.4, $metrics['coverage_ratio']); // (30,000 + 40,000) / 50,000 = 1.4x
    }
}
