<?php

declare(strict_types=1);

namespace Odden\Sales\Enums;

enum DealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Won => 'Closed Won',
            self::Lost => 'Closed Lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function isWon(): bool
    {
        return $this === self::Won;
    }

    public function isLost(): bool
    {
        return $this === self::Lost;
    }
}
