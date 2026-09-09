<?php

namespace App\Http\Controllers\User;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Movie\BookingItem;
use App\Support\Cinema\TicketQrCode;
use Carbon\CarbonImmutable;
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
        abort_unless(in_array($ticket->booking->getRawOriginal('status'), [BookingStatus::Confirmed->value, BookingStatus::Completed->value], true), 404);
        abort_unless(in_array($ticket->getRawOriginal('status'), [TicketStatus::Issued->value, TicketStatus::CheckedIn->value], true), 404);
        $rawEndsAt = $ticket->booking->screening?->getRawOriginal('ends_at');
        $verificationExpiresAt = $rawEndsAt !== null
            ? CarbonImmutable::parse((string) $rawEndsAt, 'UTC')->addHours(24)
            : now()->addHours(24);
        $verifyUrl = URL::temporarySignedRoute('user.tickets.verify', $verificationExpiresAt, ['ticket' => $ticket->ticket_code]);

        $qrCode = $ticketQrCode->render($verifyUrl);

        return view('user.tickets.show', compact('ticket', 'verifyUrl', 'qrCode'));
    }
}
