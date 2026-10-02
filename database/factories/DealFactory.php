<?php

declare(strict_types=1);

namespace Odden\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Odden\Sales\Enums\DealStatus;
use Odden\Sales\Models\Deal;
use Odden\Sales\Models\Pipeline;
use Odden\Sales\Models\PipelineStage;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Deal>
     */
    protected $model = Deal::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pipeline_id' => Pipeline::factory(),
            'stage_id' => function (array $attributes) {
                return PipelineStage::factory()->create([
                    'pipeline_id' => $attributes['pipeline_id'],
                ])->id;
            },
            'name' => fake()->company().' - '.fake()->randomElement(['New License', 'Expansion', 'Annual Renewal', 'Consulting']),
            'amount' => fake()->randomFloat(2, 5000, 250000),
            'currency' => 'USD',
            'status' => DealStatus::Open,
            'expected_close_date' => now()->addDays(fake()->numberBetween(14, 90)),
            'closed_at' => null,
            'lost_reason' => null,
            'properties' => null,
            'owner_id' => null,
            'team_id' => null,
        ];
    }

    /**
     * Indicate that the deal is Won.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealStatus::Won,
            'closed_at' => now(),
            'lost_reason' => null,
        ]);
    }

    /**
     * Indicate that the deal is Lost.
     */
    public function lost(?string $reason = 'Budget constraints'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DealStatus::Lost,
            'closed_at' => now(),
            'lost_reason' => $reason,
        ]);
    }
}
