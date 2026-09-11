<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Infrastructure\OutboxEventType;
use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Movie\Booking;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use Illuminate\Support\Facades\Auth;

final class TransitionBooking
{
    public function execute(Booking $booking, BookingStatus $target, ?string $reason = null, ?int $actorId = null): Booking
    {
        $current = BookingStatus::tryFrom((string) $booking->getRawOriginal('status'));

        if ($current === null || ! $current->canTransitionTo($target)) {
            throw new InvalidBookingTransition(__('booking.messages.invalid_transition'));
        }

        if ($current === $target) {
            return $booking;
        }

        $booking->setAttribute('status', $target);
        $booking->save();

        $booking->transitionAudits()->create([
            'actor_id' => $actorId ?? Auth::id(),
            'from_status' => $current->value,
            'to_status' => $target->value,
            'reason' => $reason ?? $booking->getAttribute('cancellation_reason'),
        ]);

        OutboxMessage::query()->create([
            'aggregate_type' => Booking::class,
            'aggregate_id' => $booking->getKey(),
            'event_type' => OutboxEventType::BookingStatusChanged,
            'payload' => [
                'booking_id' => $booking->getKey(),
                'from' => $current->value,
                'to' => $target->value,
                'locale' => app()->getLocale(),
            ],
        ]);

        if ($target === BookingStatus::Expired) {
            OutboxMessage::query()->create([
                'aggregate_type' => Booking::class,
                'aggregate_id' => $booking->getKey(),
                'event_type' => OutboxEventType::BookingExpired,
                'payload' => ['booking_id' => $booking->getKey(), 'locale' => app()->getLocale()],
            ]);
        }

        return $booking;
    }
}
