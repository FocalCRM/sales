<?php

declare(strict_types=1);

namespace Focal\Sales\Enums;

enum LostReason: string
{
    case Price = 'price';
    case Competitor = 'competitor';
    case FeatureGap = 'feature_gap';
    case Timing = 'timing';
    case Unresponsive = 'unresponsive';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'Price / Budget',
            self::Competitor => 'Lost to Competitor',
            self::FeatureGap => 'Feature Gap',
            self::Timing => 'Timing / Postponed',
            self::Unresponsive => 'Unresponsive / Ghosted',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Price => 'warning',
            self::Competitor => 'danger',
            self::FeatureGap => 'info',
            self::Timing => 'gray',
            self::Unresponsive => 'gray',
            self::Other => 'gray',
        };
    }
}
