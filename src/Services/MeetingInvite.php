<?php

declare(strict_types=1);

namespace Odden\Sales\Services;

use Carbon\CarbonImmutable;
use Odden\Core\Support\UserModel;
use Odden\Sales\Models\SalesMeetingBooking;

/**
 * Builds the iCalendar (.ics, RFC 5545) invite attached to meeting confirmations.
 */
class MeetingInvite
{
    public function ics(SalesMeetingBooking $booking): string
    {
        $link = $booking->meetingLink;
        $host = $booking->user;
        $hostEmail = $host->getAttribute('email');
        $hostEmail = is_string($hostEmail) && $hostEmail !== '' ? $hostEmail : null;
        $hostName = UserModel::displayName($host, $hostEmail ?? 'Host');

        $description = $link->description ?? '';
        $description .= ($description !== '' ? "\n\n" : '')."Booked online via /meet/{$link->slug}.";

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Odden CRM//Sales Meetings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.$this->method($booking),
            'BEGIN:VEVENT',
            "UID:{$booking->uid}@getodden",
            'DTSTAMP:'.self::utc(CarbonImmutable::now()),
            'DTSTART:'.self::utc(CarbonImmutable::instance($booking->starts_at)),
            'DTEND:'.self::utc(CarbonImmutable::instance($booking->ends_at)),
            'SUMMARY:'.self::escape("{$link->title}: {$hostName} and {$booking->invitee_name}"),
            'DESCRIPTION:'.self::escape($description),
        ];

        if ($hostEmail !== null) {
            $lines[] = 'ORGANIZER;CN='.self::param($hostName).":mailto:{$hostEmail}";
        }

        $lines[] = 'ATTENDEE;CN='.self::param($booking->invitee_name).";ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:{$booking->invitee_email}";
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'SEQUENCE:0';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * REQUEST needs an organizer, so without a host email address the event is published instead.
     */
    public function method(SalesMeetingBooking $booking): string
    {
        $hostEmail = $booking->user->getAttribute('email');

        return is_string($hostEmail) && $hostEmail !== '' ? 'REQUEST' : 'PUBLISH';
    }

    protected static function utc(CarbonImmutable $time): string
    {
        return $time->utc()->format('Ymd\THis\Z');
    }

    /**
     * Escape a TEXT value (RFC 5545 3.3.11). Every line break, CRLF, lone LF, or lone CR, becomes
     * the escaped "\n", so a value can never end its content line and start another property.
     */
    protected static function escape(string $text): string
    {
        $text = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $text);

        return preg_replace('/\r\n|\r|\n/', '\\\\n', $text) ?? '';
    }

    protected static function param(string $value): string
    {
        return '"'.str_replace(['"', "\r", "\n"], '', $value).'"';
    }

    /**
     * Fold lines longer than 75 octets, as RFC 5545 requires.
     */
    protected static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];

        while (strlen($line) > 75) {
            $cut = 75;
            // Don't split a multi-byte UTF-8 character.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $chunks[] = substr($line, 0, $cut);
            $line = ' '.substr($line, $cut);
        }

        $chunks[] = $line;

        return implode("\r\n", $chunks);
    }
}
