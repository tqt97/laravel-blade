<?php

namespace App\Support\Booking;

use App\Enums\Booking\BookingStatus;
use App\Exceptions\Booking\BookingOperationFailed;
use App\Models\Booking\Booking;

final class BookingMutationGuard
{
    public function assertHeldAndBookable(Booking $booking, string $lockedMessage = 'booking.messages.combos_locked'): void
    {
        if (BookingStatus::tryFrom((string) $booking->getRawOriginal('status')) !== BookingStatus::Held) {
            throw new BookingOperationFailed(__($lockedMessage));
        }

        $expiresAt = $booking->getRawOriginal('expires_at');
        if ($expiresAt === null || BookingClock::parseStored((string) $expiresAt)?->lessThanOrEqualTo(BookingClock::now()) !== false) {
            throw new BookingOperationFailed(__('booking.messages.booking_expired'));
        }

        $booking->loadMissing('screening');
        if (! $booking->screening?->isBookable()) {
            throw new BookingOperationFailed(__('booking.messages.screening_not_bookable'));
        }
    }
}
