<?php

namespace App\Jobs;

use App\Enums\Infrastructure\OutboxDeliveryStatus;
use App\Enums\Infrastructure\OutboxEventType;
use App\Mail\BookingConfirmationMail;
use App\Mail\BookingReminderMail;
use App\Models\Infrastructure\OutboxDelivery;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Movie\Booking;
use App\Models\User;
use App\Notifications\MovieBookingNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PublishOutboxMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return (string) $this->outboxMessageId;
    }

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
        $locale = is_array($payload) && in_array($payload['locale'] ?? null, ['en', 'vi'], true)
            ? $payload['locale']
            : config('app.locale', 'en');
        $booking = Booking::query()->with(['user', 'screening.movie'])->findOrFail($bookingId);
        /** @var User $user */
        $user = $booking->user;
        $eventType = OutboxEventType::from((string) $message->getRawOriginal('event_type'));

        $delivery = match ($eventType) {
            OutboxEventType::BookingCreated => $message->deliveries()->firstOrCreate([
                'channel' => 'booking-created',
            ], ['status' => OutboxDeliveryStatus::Pending]),
            OutboxEventType::BookingPaymentSucceeded => $message->deliveries()->firstOrCreate([
                'channel' => 'payment-succeeded',
            ], ['status' => OutboxDeliveryStatus::Pending]),
            OutboxEventType::BookingReminderDue => $message->deliveries()->firstOrCreate([
                'channel' => 'booking-reminder',
            ], ['status' => OutboxDeliveryStatus::Pending]),
            OutboxEventType::BookingExpired => $message->deliveries()->firstOrCreate([
                'channel' => 'booking-expired',
            ], ['status' => OutboxDeliveryStatus::Pending]),
            default => null,
        };

        if ($delivery !== null) {
            if ($delivery->getRawOriginal('status') === OutboxDeliveryStatus::Sent->value) {
                $message->forceFill(['published_at' => now(), 'claimed_at' => null])->save();

                return;
            }

            $claimed = OutboxDelivery::query()
                ->whereKey($delivery->getKey())
                ->where(function ($query): void {
                    $query->whereIn('status', [OutboxDeliveryStatus::Pending, OutboxDeliveryStatus::Failed])
                        ->orWhere(function ($sendingQuery): void {
                            $sendingQuery->where('status', OutboxDeliveryStatus::Sending)
                                ->where('claimed_at', '<=', now()->subMinutes((int) config('booking.outbox.delivery_lease_minutes', 60)));
                        });
                })
                ->update(['status' => OutboxDeliveryStatus::Sending, 'claimed_at' => now(), 'last_error' => null]);

            if ($claimed !== 1) {
                return;
            }

            try {
                $previousLocale = app()->getLocale();
                app()->setLocale($locale);
                try {
                    match ($eventType) {
                        OutboxEventType::BookingCreated => null,
                        OutboxEventType::BookingPaymentSucceeded => $this->sendPaymentSuccessDelivery($user, $booking),
                        OutboxEventType::BookingReminderDue => $this->sendReminderDelivery($user, $booking),
                        OutboxEventType::BookingExpired => $this->sendExpiredDelivery($user, $booking),
                    };
                } finally {
                    app()->setLocale($previousLocale);
                }
            } catch (Throwable $exception) {
                $delivery->forceFill([
                    'status' => OutboxDeliveryStatus::Failed,
                    'last_error' => $exception->getMessage(),
                ])->save();
                throw $exception;
            }
            $delivery->forceFill([
                'status' => OutboxDeliveryStatus::Sent,
                'sent_at' => now(),
            ])->save();
        }
        $message->forceFill([
            'published_at' => now(),
            'claimed_at' => null,
        ])->save();
    }

    private function sendPaymentSuccessDelivery(User $user, Booking $booking): void
    {
        $this->notifyOnce($user, $booking, 'booking_confirmed');
        Mail::to($user)->send(new BookingConfirmationMail($booking));
    }

    private function sendReminderDelivery(User $user, Booking $booking): void
    {
        $this->notifyOnce($user, $booking, 'booking_reminder');
        Mail::to($user)->send(new BookingReminderMail($booking));
    }

    private function sendExpiredDelivery(User $user, Booking $booking): void
    {
        $this->notifyOnce($user, $booking, 'booking_expired');
    }

    private function notifyOnce(User $user, Booking $booking, string $event): void
    {
        $key = $event.':'.$booking->getKey();
        if ($user->notifications()->where('data->key', $key)->exists()) {
            return;
        }

        $user->notify(new MovieBookingNotification($booking, $event));
    }

    public function failed(Throwable $exception): void
    {
        OutboxMessage::query()->whereKey($this->outboxMessageId)->update([
            'failed_at' => now(),
            'claimed_at' => null,
            'last_error' => mb_substr($exception->getMessage(), 0, 65535),
        ]);
    }
}
