<?php

namespace App\Enums\Movie\Ticketing;

enum TicketStatus: string
{
    case Issued = 'issued';
    case CheckedIn = 'checked_in';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function isCheckInEligible(): bool
    {
        return in_array($this, [self::Issued, self::CheckedIn], true);
    }
}
