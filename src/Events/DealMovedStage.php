<?php

declare(strict_types=1);

namespace Focal\Sales\Events;

use Focal\Sales\Models\Deal;
use Focal\Sales\Models\PipelineStage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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
