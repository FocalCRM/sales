<?php

declare(strict_types=1);

namespace Focal\Sales\Enums;

enum LeadRoutingStrategy: string
{
    case RoundRobin = 'round_robin';
    case QuotaWeighted = 'quota_weighted';
    case Territory = 'territory';

    public function label(): string
    {
        return match ($this) {
            self::RoundRobin => 'Round Robin (Even Distribution)',
            self::QuotaWeighted => 'Quota Weighted (Prioritize Attainment Gap)',
            self::Territory => 'Territory / Matching Criteria',
        };
    }
}
