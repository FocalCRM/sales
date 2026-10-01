<?php

declare(strict_types=1);

namespace Focal\Sales\Console\Commands;

use Focal\Sales\Enums\QuoteStatus;
use Focal\Sales\Models\Quote;
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
        $today = now()->startOfDay();

        $expiredCount = Quote::query()
            ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Approved])
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today)
            ->update([
                'status' => QuoteStatus::Expired,
            ]);

        $this->info("Successfully evaluated and expired {$expiredCount} stale quote proposal(s).");

        return self::SUCCESS;
    }
}
