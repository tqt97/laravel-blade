<?php

namespace App\Jobs;

use App\Enums\Infrastructure\OutboxDeliveryStatus;
use App\Enums\Infrastructure\OutboxEventType;
use App\Mail\BookingCreatedMail;
use App\Mail\BookingReminderMail;
use App\Mail\PaymentSucceededMail;
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
        $booking = Booking::query()->with(['user', 'screening.movie'])->findOrFail($bookingId);
        /** @var User $user */
        $user = $booking->user;
        $eventType = OutboxEventType::from((string) $message->getRawOriginal('event_type'));
        $delivery = match ($eventType) {
            OutboxEventType::BookingCreated => $message->deliveries()->firstOrCreate(['channel' => 'booking-created'], ['status' => OutboxDeliveryStatus::Pending]),
            OutboxEventType::BookingPaymentSucceeded => $message->deliveries()->firstOrCreate(['channel' => 'payment-succeeded'], ['status' => OutboxDeliveryStatus::Pending]),
            OutboxEventType::BookingReminderDue => $message->deliveries()->firstOrCreate(['channel' => 'booking-reminder'], ['status' => OutboxDeliveryStatus::Pending]),
            default => null,
        };
        if ($delivery !== null) {
            if ($delivery->getRawOriginal('status') === OutboxDeliveryStatus::Sent->value) {
                $message->forceFill(['published_at' => now()->utc(), 'claimed_at' => null])->save();

                return;
            }
            $claimed = OutboxDelivery::query()
                ->whereKey($delivery->getKey())
                ->where(function ($query): void {
                    $query->whereIn('status', [OutboxDeliveryStatus::Pending, OutboxDeliveryStatus::Failed])
                        ->orWhere(function ($sendingQuery): void {
                            $sendingQuery->where('status', OutboxDeliveryStatus::Sending)
                                ->where('claimed_at', '<=', now()->utc()->subMinutes((int) config('booking.outbox.delivery_lease_minutes', 60)));
                        });
                })
                ->update(['status' => OutboxDeliveryStatus::Sending, 'claimed_at' => now()->utc(), 'last_error' => null]);
            if ($claimed !== 1) {
                return;
            }
            try {
                match ($eventType) {
                    OutboxEventType::BookingCreated => Mail::to($booking->user)->send(new BookingCreatedMail($booking)),
                    OutboxEventType::BookingPaymentSucceeded => tap(Mail::to($user)->send(new PaymentSucceededMail($booking)), fn () => $user->notify(new MovieBookingNotification($booking, 'booking_confirmed'))),
                    OutboxEventType::BookingReminderDue => tap(Mail::to($user)->send(new BookingReminderMail($booking)), fn () => $user->notify(new MovieBookingNotification($booking, 'booking_reminder'))),
                };
            } catch (Throwable $exception) {
                $delivery->forceFill(['status' => OutboxDeliveryStatus::Failed, 'last_error' => $exception->getMessage()])->save();
                throw $exception;
            }
            $delivery->forceFill(['status' => OutboxDeliveryStatus::Sent, 'sent_at' => now()->utc()])->save();
        }
        $message->forceFill(['published_at' => now()->utc(), 'claimed_at' => null])->save();
    }

    public function failed(Throwable $exception): void
    {
        OutboxMessage::query()->whereKey($this->outboxMessageId)->update([
            'failed_at' => now()->utc(), 'claimed_at' => null, 'last_error' => mb_substr($exception->getMessage(), 0, 65535),
        ]);
    }
}
