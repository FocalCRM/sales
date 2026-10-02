<?php

declare(strict_types=1);

namespace Odden\Sales\Mail;

use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;

/**
 * Entry point for mail Sales sends: uses the odden-sales.mail.mailer mailer (or the app default).
 */
final class SalesMail
{
    public static function to(string $address, ?string $name = null): PendingMail
    {
        $mailer = config('odden-sales.mail.mailer');

        /** @var PendingMail $pending */
        $pending = Mail::mailer(is_string($mailer) && $mailer !== '' ? $mailer : null)->to(
            $name !== null && $name !== '' ? [['email' => $address, 'name' => $name]] : $address
        );

        return $pending;
    }
}
