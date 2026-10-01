<?php

declare(strict_types=1);

namespace Focal\Sales\Actions;

use Focal\Core\Enums\ActivityType;
use Focal\Sales\Enums\DealStatus;
use Focal\Sales\Enums\QuoteStatus;
use Focal\Sales\Models\PipelineStage;
use Focal\Sales\Models\Quote;
use InvalidArgumentException;

class AcceptQuoteAction
{
    /**
     * Accept and sign a quote via public token.
     */
    public function execute(string $token, string $signedByName, string $signedByEmail): Quote
    {
        /** @var Quote|null $quote */
        $quote = Quote::query()
            ->where('public_token', $token)
            ->with(['deal.pipeline.stages', 'deal.contacts'])
            ->first();

        if ($quote === null) {
            throw new InvalidArgumentException('Invalid or expired quote link.');
        }

        if ($quote->status === QuoteStatus::Accepted) {
            return $quote;
        }

        if ($quote->expires_at !== null && $quote->expires_at->isPast()) {
            $quote->update(['status' => QuoteStatus::Expired]);

            throw new InvalidArgumentException('This quote proposal has expired.');
        }

        $quote->update([
            'status' => QuoteStatus::Accepted,
            'accepted_at' => now(),
            'signed_by_name' => trim($signedByName),
            'signed_by_email' => strtolower(trim($signedByEmail)),
        ]);

        $deal = $quote->deal;
        /** @var PipelineStage|null $wonStage */
        $wonStage = $deal->pipeline->stages->firstWhere('is_closed_won', true);

        if ($wonStage !== null) {
            $deal->moveToStage($wonStage);
        }

        $deal->update([
            'status' => DealStatus::Won,
            'closed_at' => now(),
            'amount' => (float) $quote->total_amount,
        ]);

        $deal->logActivity(
            type: ActivityType::Note,
            title: "Quote #{$quote->quote_number} Accepted & Signed",
            body: "Electronically signed by {$quote->signed_by_name} ({$quote->signed_by_email}) for {$quote->currency} ".number_format((float) $quote->total_amount, 2).'.'
        );

        if ($deal->owner_id !== null) {
            $deal->logTask(
                title: "Deal Closed-Won: Quote #{$quote->quote_number} Signed!",
                dueAt: now(),
                body: "Customer {$quote->signed_by_name} ({$quote->signed_by_email}) has signed quote proposal #{$quote->quote_number} for {$quote->currency} ".number_format((float) $quote->total_amount, 2).'. Initiate customer onboarding and billing handoff immediately.',
                creatorId: (int) $deal->owner_id
            );
        }

        return $quote;
    }
}
