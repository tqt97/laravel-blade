<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cinema\BookingItem;
use App\Support\Cinema\TicketQrCode;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

final class TicketController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(BookingItem $ticket, TicketQrCode $ticketQrCode): View
    {
        $ticket->load(['booking.user', 'booking.screening.movie', 'booking.screening.room', 'screeningSeat.seat']);
        abort_unless($ticket->booking->getAttribute('user_id') === auth()->id(), 403);
        $verifyUrl = URL::temporarySignedRoute('user.tickets.verify', now()->addHours(24), ['ticket' => $ticket->ticket_code]);

        $qrCode = $ticketQrCode->render($verifyUrl);

        return view('user.tickets.show', compact('ticket', 'verifyUrl', 'qrCode'));
    }
}
