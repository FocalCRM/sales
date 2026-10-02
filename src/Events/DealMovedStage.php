<?php

declare(strict_types=1);

namespace Odden\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Odden\Sales\Models\Deal;
use Odden\Sales\Models\PipelineStage;

class DealMovedStage
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Deal $deal,
        public ?PipelineStage $fromStage,
        public PipelineStage $toStage,
        public int|string|null $userId = null
    ) {}
}
