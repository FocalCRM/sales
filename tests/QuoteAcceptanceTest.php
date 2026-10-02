<?php

declare(strict_types=1);

namespace Odden\Sales\Tests;

use Odden\Sales\Actions\AcceptQuoteAction;
use Odden\Sales\Enums\DealStatus;
use Odden\Sales\Enums\QuoteStatus;
use Odden\Sales\Enums\StageAutomationActionType;
use Odden\Sales\Exceptions\QuoteNotAcceptableException;
use Odden\Sales\Exceptions\StageRequirementException;
use Odden\Sales\Models\Deal;
use Odden\Sales\Models\Pipeline;
use Odden\Sales\Models\PipelineStage;
use Odden\Sales\Models\Quote;
use Odden\Sales\Models\StageAutomation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;

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

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function quoteOnOpenDeal(array $attributes = []): Quote
    {
        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->first()->id,
            'status' => DealStatus::Open,
        ]);

        return Quote::factory()->create(array_merge([
            'deal_id' => $deal->id,
            'status' => QuoteStatus::Sent,
            'total_amount' => 5000.00,
        ], $attributes));
    }

    /**
     * @return array<string, array{QuoteStatus}>
     */
    public static function nonAcceptableStatuses(): array
    {
        return [
            'declined' => [QuoteStatus::Declined],
            'expired' => [QuoteStatus::Expired],
        ];
    }

    #[DataProvider('nonAcceptableStatuses')]
    public function test_terminal_quotes_cannot_be_accepted_and_hide_the_sign_form(QuoteStatus $status): void
    {
        $quote = $this->quoteOnOpenDeal(['status' => $status]);

        $page = $this->get("/quotes/{$quote->public_token}");
        $page->assertSuccessful();
        $page->assertDontSee('Accept & Sign Proposal', false);
        $page->assertSee('no longer available for acceptance');

        $response = $this->post("/quotes/{$quote->public_token}/accept", [
            'signed_name' => 'Alice Johnson',
            'signed_email' => 'alice@acme.com',
            'agree_terms' => '1',
        ]);

        $response->assertRedirect("/quotes/{$quote->public_token}");
        $response->assertSessionHasErrors('error');

        $this->assertSame($status, $quote->refresh()->status);
        $this->assertNull($quote->signed_by_name);
        $this->assertSame(DealStatus::Open, $quote->deal->refresh()->status);
    }

    public function test_already_accepted_quote_cannot_be_signed_again(): void
    {
        $quote = $this->quoteOnOpenDeal([
            'status' => QuoteStatus::Accepted,
            'signed_by_name' => 'Original Signer',
            'signed_by_email' => 'original@acme.com',
        ]);

        $this->expectException(QuoteNotAcceptableException::class);

        try {
            app(AcceptQuoteAction::class)->execute($quote->public_token, 'Someone Else', 'else@acme.com');
        } finally {
            $this->assertSame('Original Signer', $quote->refresh()->signed_by_name);
        }
    }

    public function test_quote_can_be_accepted_on_its_expiry_day(): void
    {
        $quote = $this->quoteOnOpenDeal(['expires_at' => today()]);

        $this->get("/quotes/{$quote->public_token}")->assertSee('Accept & Sign Proposal', false);

        $this->post("/quotes/{$quote->public_token}/accept", [
            'signed_name' => 'Alice Johnson',
            'signed_email' => 'alice@acme.com',
            'agree_terms' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(QuoteStatus::Accepted, $quote->refresh()->status);
    }

    public function test_quote_past_its_expiry_date_cannot_be_accepted_and_expire_command_agrees(): void
    {
        $expiresToday = $this->quoteOnOpenDeal(['expires_at' => today()]);
        $pastExpiry = $this->quoteOnOpenDeal(['expires_at' => today()->subDay()]);

        $this->artisan('sales:expire-quotes')->assertSuccessful();

        $this->assertSame(QuoteStatus::Sent, $expiresToday->refresh()->status);
        $this->assertSame(QuoteStatus::Expired, $pastExpiry->refresh()->status);

        // A past-expiry quote the command has not reached yet is refused and marked expired.
        $notYetSwept = $this->quoteOnOpenDeal(['expires_at' => today()->subDay()]);

        $this->get("/quotes/{$notYetSwept->public_token}")->assertDontSee('Accept & Sign Proposal', false);

        $this->post("/quotes/{$notYetSwept->public_token}/accept", [
            'signed_name' => 'Alice Johnson',
            'signed_email' => 'alice@acme.com',
            'agree_terms' => '1',
        ])->assertSessionHasErrors('error');

        $this->assertSame(QuoteStatus::Expired, $notYetSwept->refresh()->status);
        $this->assertSame(DealStatus::Open, $notYetSwept->deal->refresh()->status);
    }

    public function test_failing_won_stage_requirement_rolls_back_acceptance_and_shows_a_generic_error(): void
    {
        Exceptions::fake();

        $quote = $this->quoteOnOpenDeal();

        /** @var PipelineStage $wonStage */
        $wonStage = $quote->deal->pipeline->stages()->where('is_closed_won', true)->firstOrFail();

        StageAutomation::create([
            'stage_id' => $wonStage->id,
            'name' => 'Require Products for Won',
            'action_type' => StageAutomationActionType::RequireDealProducts,
            'is_active' => true,
        ]);

        $response = $this->post("/quotes/{$quote->public_token}/accept", [
            'signed_name' => 'Alice Johnson',
            'signed_email' => 'alice@acme.com',
            'agree_terms' => '1',
        ]);

        $response->assertRedirect("/quotes/{$quote->public_token}");
        $response->assertSessionHasErrors('error');
        $message = (string) session('errors')->first('error');
        $this->assertStringNotContainsString('requires at least one line item', $message);
        $this->assertStringNotContainsString($wonStage->name, $message);

        Exceptions::assertReported(StageRequirementException::class);

        $quote->refresh();
        $this->assertSame(QuoteStatus::Sent, $quote->status);
        $this->assertNull($quote->accepted_at);
        $this->assertNull($quote->signed_by_name);

        $deal = $quote->deal->refresh();
        $this->assertSame(DealStatus::Open, $deal->status);
        $this->assertNotSame($wonStage->id, $deal->stage_id);
    }
}
