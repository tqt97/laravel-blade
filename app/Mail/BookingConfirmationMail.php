<?php

namespace App\Mail;

use App\Models\Movie\Booking;
use App\Support\Cinema\TicketQrCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

final class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<int, array{seat: string, verify_url: string, qr_base64: string}> */
    public readonly array $tickets;

    public function __construct(public readonly Booking $booking)
    {
        $booking->loadMissing(['items.screeningSeat.seat', 'screening.movie', 'screening.room', 'concessions.concession']);
        $endsAt = $booking->screening->ends_at ?? now()->addDay();
        $qrCode = new TicketQrCode;
        $this->tickets = $booking->items->map(function ($item) use ($endsAt, $qrCode): array {
            $verifyUrl = URL::temporarySignedRoute('user.tickets.verify', $endsAt->copy()->addHours(24), ['ticket' => $item->ticket_code]);

            return [
                'seat' => (string) ($item->screeningSeat->seat->row_label ?? '').(string) ($item->screeningSeat->seat->seat_number ?? ''),
                'verify_url' => $verifyUrl,
                'qr_base64' => base64_encode($qrCode->render($verifyUrl, 180)),
            ];
        })->all();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('booking.mail.confirmation_subject', ['movie' => $this->booking->screening->movie->title ?? 'Movie']));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-confirmation');
    }

    public function headers(): Headers
    {
        return new Headers(
            messageId: 'booking-confirmation-'.$this->booking->getKey().'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
        );
    }
}
