<?php

declare(strict_types=1);

namespace Odden\Sales\Console\Commands;

use Odden\Sales\Enums\QuoteStatus;
use Odden\Sales\Models\Quote;
use Illuminate\Console\Command;

class ExpireStaleQuotesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:expire-quotes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire stale quotes and proposals that have exceeded their validity expiration date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Same rule as public acceptance: a quote is valid through its expiry day.
        $expiredCount = Quote::query()
            ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Approved])
            ->pastExpiryDate()
            ->update([
                'status' => QuoteStatus::Expired,
            ]);

        $this->info("Successfully evaluated and expired {$expiredCount} stale quote proposal(s).");

        return self::SUCCESS;
    }
}
