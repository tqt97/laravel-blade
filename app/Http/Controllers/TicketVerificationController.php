<?php

namespace App\Http\Controllers;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Models\Movie\BookingItem;
use Illuminate\View\View;

final class TicketVerificationController extends Controller
{
    public function __invoke(string $ticket): View
    {
        $ticket = BookingItem::query()
            ->with(['booking.screening.movie', 'booking.screening.room', 'screeningSeat.seat'])
            ->where('ticket_code', $ticket)
            ->firstOrFail();

        abort_unless(
            BookingStatus::tryFrom((string) $ticket->booking->getRawOriginal('status'))?->isTicketAccessible() === true
            && TicketStatus::tryFrom((string) $ticket->getRawOriginal('status'))?->isCheckInEligible() === true,
            404,
        );

        return view('user.tickets.verify', compact('ticket'));
    }
}
