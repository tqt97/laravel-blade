<?php

namespace App\Jobs;

use App\Mail\BookingCreatedMail;
use App\Mail\PaymentSucceededMail;
use App\Models\Cinema\Booking;
use App\Models\Infrastructure\OutboxMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PublishOutboxMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $outboxMessageId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = OutboxMessage::query()->find($this->outboxMessageId);
        if ($message === null || $message->published_at !== null || $message->failed_at !== null) {
            return;
        }
        $message->increment('attempts');
        $message->refresh();
        $payload = $message->getAttribute('payload');
        $bookingId = is_array($payload) ? (int) ($payload['booking_id'] ?? 0) : 0;
        $booking = Booking::query()->with(['user', 'screening.movie'])->findOrFail($bookingId);
        match ($message->event_type) {
            'booking.created' => Mail::to($booking->user)->send(new BookingCreatedMail($booking)),
            'booking.payment_succeeded' => Mail::to($booking->user)->send(new PaymentSucceededMail($booking)),
            default => null,
        };
        $message->forceFill(['published_at' => now()->utc(), 'claimed_at' => null])->save();
    }

    public function failed(Throwable $exception): void
    {
        OutboxMessage::query()->whereKey($this->outboxMessageId)->update([
            'failed_at' => now()->utc(), 'claimed_at' => null, 'last_error' => mb_substr($exception->getMessage(), 0, 65535),
        ]);
    }
}
