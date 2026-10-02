<?php

declare(strict_types=1);

namespace Odden\Sales\Actions;

use Odden\Sales\Models\Deal;

class SyncDealAmountAction
{
    /**
     * Recalculate and update the deal aggregate amount from its product line items.
     */
    public function execute(Deal $deal): Deal
    {
        $total = $deal->products()->exists()
            ? (float) $deal->products()->sum('total_price')
            : 0.00;

        $deal->updateQuietly(['amount' => round($total, 2)]);

        return $deal;
    }
}
