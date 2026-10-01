<?php

declare(strict_types=1);

namespace Focal\Sales\Actions;

use Focal\Sales\Enums\DealStatus;
use Focal\Sales\Models\Deal;
use Illuminate\Database\Eloquent\Collection;

class CalculatePipelineForecastAction
{
    /**
     * Calculate pipeline sales forecast and conversion metrics.
     *
     * @return array{
     *     open_value: float,
     *     open_count: int,
     *     weighted_forecast: float,
     *     won_value: float,
     *     won_count: int,
     *     lost_count: int,
     *     win_rate: float,
     *     average_deal_size: float,
     *     lost_reasons: array<string, int>,
     *     stale_deals_count: int
     * }
     */
    public function execute(?int $pipelineId = null): array
    {
        $dealsTable = config('focal-sales.tables.deals', 'focal_deals');
        $stagesTable = config('focal-sales.tables.stages', 'focal_pipeline_stages');

        $baseQuery = Deal::query();
        if ($pipelineId !== null) {
            $baseQuery->where("{$dealsTable}.pipeline_id", $pipelineId);
        }

        // 1. Open deals and value
        $openQuery = (clone $baseQuery)->where("{$dealsTable}.status", DealStatus::Open->value);
        $openCount = $openQuery->count();
        $openValue = (float) $openQuery->sum("{$dealsTable}.amount");

        // 2. Weighted forecast value based on stage win probability
        $openDeals = (clone $baseQuery)
            ->where('status', DealStatus::Open->value)
            ->with(['stage', 'stageHistory'])
            ->get();

        $weightedForecast = (float) $openDeals->sum(
            fn (Deal $deal): float => (float) $deal->amount * (($deal->stage->probability ?? 0) / 100.0)
        );

        // 3. Won deals
        $wonQuery = (clone $baseQuery)->where("{$dealsTable}.status", DealStatus::Won->value);
        $wonCount = $wonQuery->count();
        $wonValue = (float) $wonQuery->sum("{$dealsTable}.amount");

        // 4. Lost deals & Win rate
        $lostQuery = (clone $baseQuery)->where("{$dealsTable}.status", DealStatus::Lost->value);
        $lostCount = $lostQuery->count();

        $closedCount = $wonCount + $lostCount;
        $winRate = $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 1) : 0.0;

        // 5. Average deal size
        $averageDealSize = (float) ($baseQuery->avg("{$dealsTable}.amount") ?? 0.0);

        // 6. Lost reasons breakdown
        $lostReasons = [];
        /** @var Collection<int, Deal> $lostDeals */
        $lostDeals = (clone $baseQuery)->where("{$dealsTable}.status", DealStatus::Lost->value)->get();
        foreach ($lostDeals as $lostDeal) {
            $reason = $lostDeal->lost_reason ?: 'unspecified';
            $lostReasons[$reason] = ($lostReasons[$reason] ?? 0) + 1;
        }

        // 7. Stale deals count
        $staleCount = 0;
        foreach ($openDeals as $openDeal) {
            if ($openDeal->isRotten()) {
                $staleCount++;
            }
        }

        return [
            'open_value' => round($openValue, 2),
            'open_count' => $openCount,
            'weighted_forecast' => round($weightedForecast, 2),
            'won_value' => round($wonValue, 2),
            'won_count' => $wonCount,
            'lost_count' => $lostCount,
            'win_rate' => $winRate,
            'average_deal_size' => round($averageDealSize, 2),
            'lost_reasons' => $lostReasons,
            'stale_deals_count' => $staleCount,
        ];
    }
}
