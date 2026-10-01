<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Core\Enums\ActivityType;
use Focal\Core\Models\Contact;
use Focal\Sales\Actions\CalculateDealHealthScoreAction;
use Focal\Sales\Enums\DealStatus;
use Focal\Sales\Enums\QuoteStatus;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\Pipeline;
use Focal\Sales\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DealHealthScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_threaded_deal_with_recent_activity_scores_high(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->first()->id,
            'status' => DealStatus::Open,
            'expected_close_date' => now()->addDays(30),
        ]);

        $contact1 = Contact::factory()->create();
        $contact2 = Contact::factory()->create();
        $deal->contacts()->attach([$contact1->id, $contact2->id], [
            'parent_type' => $deal->getMorphClass(),
            'child_type' => $contact1->getMorphClass(),
        ]);

        $deal->logActivity(ActivityType::Call, 'Strategy sync with buyer team');

        Quote::factory()->create([
            'deal_id' => $deal->id,
            'status' => QuoteStatus::Sent,
        ]);

        $action = new CalculateDealHealthScoreAction;
        $health = $action->execute($deal);

        $this->assertGreaterThanOrEqual(75, $health['score']);
        $this->assertSame('strong', $health['status']);
        $this->assertSame('success', $health['badge_color']);
        $this->assertStringContainsString('Strong', $health['badge_label']);
    }

    public function test_stalled_deal_without_activity_scores_at_risk(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $stage = $pipeline->stages->first();
        $stage->update(['rot_after_days' => 5]);

        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'status' => DealStatus::Open,
            'created_at' => now()->subDays(15),
        ]);

        $action = new CalculateDealHealthScoreAction;
        $health = $action->execute($deal);

        $this->assertLessThan(50, $health['score']);
        $this->assertContains('Deal Rotting', array_column($health['factors'], 'name'));
        $this->assertNotEmpty($health['recommendations']);
    }
}
