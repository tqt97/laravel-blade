<?php

namespace App\Http\Controllers\User;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Movie\BookingItem;
use App\Support\Cinema\TicketQrCode;
use App\Support\Time\BookingClock;
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

        abort_unless(BookingStatus::tryFrom((string) $ticket->booking->getRawOriginal('status'))?->isTicketAccessible() === true, 404);

        abort_unless(TicketStatus::tryFrom((string) $ticket->getRawOriginal('status'))?->isCheckInEligible() === true, 404);

        $rawEndsAt = $ticket->booking->screening?->getRawOriginal('ends_at');
        $verificationGraceHours = (int) config('booking.ticket.verification_grace_hours', 24);
        $verificationExpiresAt = $rawEndsAt !== null
            ? BookingClock::parseStored((string) $rawEndsAt)?->addHours($verificationGraceHours)
            : now()->addHours($verificationGraceHours);
        $verifyUrl = URL::temporarySignedRoute('user.tickets.verify', $verificationExpiresAt, ['ticket' => $ticket->ticket_code]);

        $qrCode = $ticketQrCode->render($verifyUrl);

        return view('user.tickets.show', compact('ticket', 'verifyUrl', 'qrCode'));
    }
}
