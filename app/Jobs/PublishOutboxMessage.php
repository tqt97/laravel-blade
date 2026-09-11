<?php

namespace App\Jobs;

use App\Contracts\OutboxDeliveryHandler;
use App\Enums\Infrastructure\OutboxDeliveryStatus;
use App\Enums\Infrastructure\OutboxEventType;
use App\Models\Infrastructure\OutboxDelivery;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Movie\Booking;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishOutboxMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $uniqueFor;

    public function uniqueId(): string
    {
        return (string) $this->outboxMessageId;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $outboxMessageId)
    {
        $this->tries = (int) config('booking.outbox.tries', 3);
        $this->uniqueFor = (int) config('booking.outbox.unique_for_seconds', 3600);
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
        $locale = is_array($payload) && in_array($payload['locale'] ?? null, config('app.supported_locales', []), true)
            ? $payload['locale']
            : config('app.locale', 'en');
        $booking = Booking::query()->with(['user', 'screening.movie'])->findOrFail($bookingId);
        /** @var User $user */
        $user = $booking->user;
        $eventType = OutboxEventType::from((string) $message->getRawOriginal('event_type'));

        $channel = $eventType->channel();
        if ($channel === null) {
            $message->forceFill(['published_at' => now(), 'claimed_at' => null])->save();

            return;
        }

        $delivery = $message->deliveries()->firstOrCreate([
            'channel' => $channel,
        ], ['status' => OutboxDeliveryStatus::Pending]);

        $idempotencyKey = $eventType->value.':'.$booking->getKey();
        $messageId = $idempotencyKey.'@'.parse_url((string) config('app.url'), PHP_URL_HOST);
        if (blank($delivery->getAttribute('idempotency_key'))) {
            $delivery->forceFill(['idempotency_key' => $idempotencyKey])->save();
        }
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

        $delivery->increment('attempts');
        Log::info('outbox.delivery_started', [
            'outbox_message_id' => $message->getKey(),
            'delivery_id' => $delivery->getKey(),
            'event_type' => $eventType->value,
            'idempotency_key' => $idempotencyKey,
            'attempt' => (int) $delivery->fresh()->getAttribute('attempts'),
        ]);

        try {
            $previousLocale = app()->getLocale();
            app()->setLocale($locale);
            try {
                $handlerClass = $eventType->deliveryHandler();
                if ($handlerClass !== null) {
                    /** @var OutboxDeliveryHandler $handler */
                    $handler = app($handlerClass);
                    $handler->execute($user, $booking);
                }
            } finally {
                app()->setLocale($previousLocale);
            }
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => OutboxDeliveryStatus::Failed,
                'last_error' => $exception->getMessage(),
            ])->save();
            Log::warning('outbox.delivery_failed', [
                'outbox_message_id' => $message->getKey(),
                'delivery_id' => $delivery->getKey(),
                'idempotency_key' => $idempotencyKey,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
        $delivery->forceFill([
            'status' => OutboxDeliveryStatus::Sent,
            'sent_at' => now(),
            'message_id' => $messageId,
        ])->save();
        Log::info('outbox.delivery_sent', [
            'outbox_message_id' => $message->getKey(),
            'delivery_id' => $delivery->getKey(),
            'idempotency_key' => $idempotencyKey,
        ]);
        $message->forceFill([
            'published_at' => now(),
            'claimed_at' => null,
        ])->save();
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
