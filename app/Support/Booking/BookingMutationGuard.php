<?php

namespace App\Support\Booking;

use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Movie\Booking;
use App\Support\Time\BookingClock;

final class BookingMutationGuard
{
    public function assertHeldAndBookable(Booking $booking, string $lockedMessage = 'booking.messages.combos_locked'): void
    {
        if (BookingStatus::tryFrom((string) $booking->getRawOriginal('status')) !== BookingStatus::Held) {
            throw new Exceptions\BookingOperationFailed(__($lockedMessage));
        }

        $expiresAt = $booking->getRawOriginal('expires_at');
        if ($expiresAt === null || BookingClock::parseStored((string) $expiresAt)?->lessThanOrEqualTo(BookingClock::now()) !== false) {
            throw new Exceptions\BookingOperationFailed(__('booking.messages.booking_expired'));
        }

        $booking->loadMissing('screening');
        if (! $booking->screening?->isBookable()) {
            throw new Exceptions\BookingOperationFailed(__('booking.messages.screening_not_bookable'));
        }
    }
}
