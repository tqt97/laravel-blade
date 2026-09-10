<?php

namespace App\Observers\Movie;

use App\Enums\Infrastructure\OutboxEventType;
use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Movie\Booking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class BookingObserver
{
    public function created(Booking $booking): void
    {
        OutboxMessage::query()->create([
            'aggregate_type' => Booking::class,
            'aggregate_id' => $booking->getKey(),
            'event_type' => OutboxEventType::BookingCreated,
            'payload' => [
                'booking_id' => $booking->getKey(),
                'status' => BookingStatus::Held->value,
                'locale' => app()->getLocale(),
            ],
        ]);
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        $fromStatus = (string) $booking->getRawOriginal('status');
        $status = $booking->getAttribute('status');
        $toStatus = $status instanceof BookingStatus ? $status->value : (string) $status;

        $booking->transitionAudits()->create([
            'actor_id' => Auth::id(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $booking->getAttribute('cancellation_reason'),
        ]);

        OutboxMessage::query()->create([
            'aggregate_type' => Booking::class,
            'aggregate_id' => $booking->getKey(),
            'event_type' => OutboxEventType::BookingStatusChanged,
            'payload' => [
                'booking_id' => $booking->getKey(),
                'from' => $fromStatus,
                'to' => $toStatus,
                'locale' => app()->getLocale(),
            ],
        ]);

        if ($toStatus === BookingStatus::Expired->value) {
            OutboxMessage::query()->create([
                'aggregate_type' => Booking::class,
                'aggregate_id' => $booking->getKey(),
                'event_type' => OutboxEventType::BookingExpired,
                'payload' => ['booking_id' => $booking->getKey(), 'locale' => app()->getLocale()],
            ]);
        }

        Log::info('booking.status_changed', [
            'booking_id' => $booking->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => Auth::id(),
        ]);
    }
}
