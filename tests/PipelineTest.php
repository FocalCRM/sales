<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Sales\Models\Pipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_pipeline_with_stages(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create([
            'name' => 'Enterprise Sales',
            'code' => 'enterprise_sales',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('focal_pipelines', [
            'id' => $pipeline->id,
            'code' => 'enterprise_sales',
            'is_default' => 1,
        ]);

        $this->assertCount(6, $pipeline->stages);
        $this->assertSame('Discovery', $pipeline->stages->first()->name);
        $this->assertSame('Closed Lost', $pipeline->stages->last()->name);
    }

    public function test_can_retrieve_default_pipeline_and_stage(): void
    {
        Pipeline::factory()->create(['name' => 'Inbound Self-Serve', 'is_default' => false]);
        $default = Pipeline::factory()->withStages()->create(['name' => 'Default Direct Sales', 'is_default' => true]);

        $retrieved = Pipeline::query()->default()->first();

        $this->assertNotNull($retrieved);
        $this->assertSame($default->id, $retrieved->id);

        $defaultStage = $retrieved->defaultStage();
        $this->assertNotNull($defaultStage);
        $this->assertSame('Discovery', $defaultStage->name);
    }

    public function test_adding_stage_increments_sort_order_automatically(): void
    {
        $pipeline = Pipeline::factory()->create();

        $stage1 = $pipeline->addStage(['name' => 'Lead In', 'code' => 'lead_in', 'probability' => 10]);
        $stage2 = $pipeline->addStage(['name' => 'Demo Scheduled', 'code' => 'demo_scheduled', 'probability' => 30]);

        $this->assertTrue($stage2->sort_order > $stage1->sort_order);
    }
}
