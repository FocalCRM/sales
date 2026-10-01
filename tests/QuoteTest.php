<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Sales\Actions\GenerateQuoteFromDealAction;
use Focal\Sales\Enums\QuoteStatus;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\DealProduct;
use Focal\Sales\Models\Pipeline;
use Focal\Sales\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_quote_from_deal_products(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages()->firstOrFail()->id,
        ]);

        DealProduct::create([
            'deal_id' => $deal->id,
            'name' => 'Focal Enterprise License',
            'sku' => 'FOCAL-ENT',
            'unit_price' => 12000.00,
            'quantity' => 1,
            'discount_percent' => 0.00,
        ]);

        DealProduct::create([
            'deal_id' => $deal->id,
            'name' => 'Implementation Support',
            'unit_price' => 3000.00,
            'quantity' => 1,
            'discount_percent' => 10.00, // $2,700
        ]);

        $action = new GenerateQuoteFromDealAction;
        $quote = $action->execute($deal, 'Annual Enterprise Contract');

        $this->assertSame('Annual Enterprise Contract', $quote->title);
        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertEquals(14700.00, $quote->total_amount);
        $this->assertCount(2, $quote->items);
        $this->assertNotNull($quote->public_token);
        $this->assertStringStartsWith('Q-', $quote->quote_number);
    }

    public function test_quote_can_be_accepted_with_digital_signature(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages()->firstOrFail()->id,
        ]);

        $quote = Quote::factory()->create([
            'deal_id' => $deal->id,
            'total_amount' => 5000.00,
        ]);

        $this->assertFalse($quote->status->isAccepted());

        $quote->accept('Jane Doe', 'jane@example.com');

        $this->assertTrue($quote->fresh()->status->isAccepted());
        $this->assertSame('Jane Doe', $quote->fresh()->signed_by_name);
        $this->assertSame('jane@example.com', $quote->fresh()->signed_by_email);
        $this->assertNotNull($quote->fresh()->accepted_at);
    }

    public function test_quote_expiration_detection(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages()->firstOrFail()->id,
        ]);

        $activeQuote = Quote::factory()->create([
            'deal_id' => $deal->id,
            'expires_at' => now()->addDays(5),
        ]);

        $expiredQuote = Quote::factory()->create([
            'deal_id' => $deal->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($activeQuote->isExpired());
        $this->assertTrue($expiredQuote->isExpired());
    }
}
