<?php

declare(strict_types=1);

namespace Odden\Sales\Database\Factories;

use Odden\Sales\Models\Pipeline;
use Odden\Sales\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PipelineStage>
     */
    protected $model = PipelineStage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'pipeline_id' => Pipeline::factory(),
            'name' => ucwords($name),
            'code' => str_replace(' ', '_', strtolower($name)),
            'probability' => fake()->randomElement([10, 20, 40, 60, 80]),
            'sort_order' => fake()->numberBetween(1, 10),
            'is_closed_won' => false,
            'is_closed_lost' => false,
        ];
    }

    /**
     * Indicate that the stage is closed won.
     */
    public function closedWon(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Closed Won',
            'code' => 'closed_won',
            'probability' => 100,
            'is_closed_won' => true,
            'is_closed_lost' => false,
        ]);
    }

    /**
     * Indicate that the stage is closed lost.
     */
    public function closedLost(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Closed Lost',
            'code' => 'closed_lost',
            'probability' => 0,
            'is_closed_won' => false,
            'is_closed_lost' => true,
        ]);
    }
}
