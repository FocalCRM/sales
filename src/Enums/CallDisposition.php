<?php

declare(strict_types=1);

namespace Odden\Sales\Enums;

enum CallDisposition: string
{
    case Connected = 'connected';
    case LeftVoicemail = 'left_voicemail';
    case Busy = 'busy';
    case Gatekeeper = 'gatekeeper';
    case WrongNumber = 'wrong_number';
    case NoAnswer = 'no_answer';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::LeftVoicemail => 'Left Voicemail',
            self::Busy => 'Busy',
            self::Gatekeeper => 'Gatekeeper',
            self::WrongNumber => 'Wrong Number',
            self::NoAnswer => 'No Answer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::LeftVoicemail => 'info',
            self::Busy, self::Gatekeeper => 'warning',
            self::WrongNumber, self::NoAnswer => 'danger',
        };
    }
}
