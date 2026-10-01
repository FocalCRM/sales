<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Sales\Enums\DealStatus;
use Focal\Sales\Enums\QuoteStatus;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\Pipeline;
use Focal\Sales\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_public_quote_portal(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->first()->id,
            'name' => 'Acme Annual Agreement',
        ]);

        $quote = Quote::factory()->create([
            'deal_id' => $deal->id,
            'status' => QuoteStatus::Sent,
            'subtotal' => 10000.00,
            'total_amount' => 10000.00,
            'public_token' => 'test-quote-token-xyz',
        ]);

        $response = $this->get("/quotes/{$quote->public_token}");

        $response->assertSuccessful();
        $response->assertSee($quote->quote_number);
        $response->assertSee('Acme Annual Agreement');
        $response->assertSee('Accept & Sign Proposal', false);
    }

    public function test_can_accept_and_sign_quote_proposal(): void
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->first()->id,
            'status' => DealStatus::Open,
        ]);

        $quote = Quote::factory()->create([
            'deal_id' => $deal->id,
            'status' => QuoteStatus::Sent,
            'total_amount' => 12500.00,
            'public_token' => 'sign-token-123',
        ]);

        $response = $this->post("/quotes/{$quote->public_token}/accept", [
            'signed_name' => 'Alice Johnson',
            'signed_email' => 'alice@acme.com',
            'agree_terms' => '1',
        ]);

        $response->assertRedirect("/quotes/{$quote->public_token}");
        $response->assertSessionHas('status');

        $quote->refresh();
        $this->assertSame(QuoteStatus::Accepted, $quote->status);
        $this->assertSame('Alice Johnson', $quote->signed_by_name);
        $this->assertSame('alice@acme.com', $quote->signed_by_email);
        $this->assertNotNull($quote->accepted_at);

        $deal->refresh();
        $this->assertSame(DealStatus::Won, $deal->status);
        $this->assertSame(12500.00, (float) $deal->amount);
    }
}
