<?php

declare(strict_types=1);

namespace Odden\Sales\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Thrown when a meeting is booked at a time that is not an open slot on the link: outside its
 * working hours, in the past, beyond the booking window, or already taken.
 */
class MeetingSlotUnavailableException extends RuntimeException
{
    public static function at(CarbonInterface $startsAt): self
    {
        return new self("The meeting slot at {$startsAt->toIso8601String()} is not available.");
    }
}
