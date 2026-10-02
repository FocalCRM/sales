<?php

declare(strict_types=1);

namespace Odden\Sales\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a quote cannot be accepted (unknown link, already accepted, declined or expired).
 * Its messages are safe to show to the public visitor.
 */
class QuoteNotAcceptableException extends InvalidArgumentException
{
    //
}
