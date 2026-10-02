<?php

declare(strict_types=1);

namespace Odden\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Odden\Sales\Models\Pipeline;
use Odden\Sales\Models\PipelineStage;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Pipeline>
     */
    protected $model = Pipeline::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' Pipeline';

        return [
            'name' => ucwords($name),
            'code' => str_replace(' ', '_', strtolower($name)),
            'is_default' => false,
            'is_active' => true,
            'team_id' => null,
        ];
    }

    /**
     * Configure pipeline with standard sales stages.
     */
    public function withStages(): static
    {
        return $this->afterCreating(function (Pipeline $pipeline): void {
            $stages = [
                ['name' => 'Discovery', 'code' => 'discovery', 'probability' => 20, 'sort_order' => 1, 'is_closed_won' => false, 'is_closed_lost' => false],
                ['name' => 'Qualification', 'code' => 'qualification', 'probability' => 40, 'sort_order' => 2, 'is_closed_won' => false, 'is_closed_lost' => false],
                ['name' => 'Proposal Sent', 'code' => 'proposal_sent', 'probability' => 60, 'sort_order' => 3, 'is_closed_won' => false, 'is_closed_lost' => false],
                ['name' => 'Negotiation', 'code' => 'negotiation', 'probability' => 80, 'sort_order' => 4, 'is_closed_won' => false, 'is_closed_lost' => false],
                ['name' => 'Closed Won', 'code' => 'closed_won', 'probability' => 100, 'sort_order' => 5, 'is_closed_won' => true, 'is_closed_lost' => false],
                ['name' => 'Closed Lost', 'code' => 'closed_lost', 'probability' => 0, 'sort_order' => 6, 'is_closed_won' => false, 'is_closed_lost' => true],
            ];

            foreach ($stages as $stageData) {
                PipelineStage::create(array_merge($stageData, [
                    'pipeline_id' => $pipeline->id,
                ]));
            }
        });
    }

    /**
     * Set pipeline as default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
