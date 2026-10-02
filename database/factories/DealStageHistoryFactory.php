<?php

declare(strict_types=1);

namespace Odden\Sales\Database\Factories;

use Odden\Sales\Models\Deal;
use Odden\Sales\Models\DealStageHistory;
use Odden\Sales\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealStageHistory>
 */
class DealStageHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<DealStageHistory>
     */
    protected $model = DealStageHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deal_id' => Deal::factory(),
            'from_stage_id' => null,
            'to_stage_id' => PipelineStage::factory(),
            'user_id' => null,
            'duration_in_stage_seconds' => null,
            'entered_at' => now(),
            'exited_at' => null,
        ];
    }
}
