<?php

declare(strict_types=1);

namespace Odden\Sales\Mail;

/**
 * Applies the odden-sales.mail.* queue connection and queue to a queued mailable, and defers
 * dispatch until any surrounding database transaction commits. The mailer is chosen when
 * sending, through SalesMail::to().
 */
trait ConfiguresSalesMail
{
    protected function configureSalesMail(): void
    {
        $connection = config('odden-sales.mail.connection');
        $queue = config('odden-sales.mail.queue');

        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }

        $this->afterCommit();
    }

    /**
     * The configured odden-sales "from" address, or null to use the app's mail.from.
     *
     * @return array{0: string, 1: string|null}|null
     */
    protected static function configuredFrom(): ?array
    {
        $address = config('odden-sales.mail.from.address');

        if (! is_string($address) || $address === '') {
            return null;
        }

        $name = config('odden-sales.mail.from.name');

        return [$address, is_string($name) && $name !== '' ? $name : null];
    }
}
