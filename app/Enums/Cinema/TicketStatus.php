<?php

namespace App\Enums\Cinema;

enum TicketStatus: string
{
    case Issued = 'issued';
    case CheckedIn = 'checked_in';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
