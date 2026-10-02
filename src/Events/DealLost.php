<?php

declare(strict_types=1);

namespace Odden\Sales\Events;

use Odden\Sales\Models\Deal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealLost
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Deal $deal,
        public ?string $reason = null,
        public int|string|null $userId = null
    ) {}
}
