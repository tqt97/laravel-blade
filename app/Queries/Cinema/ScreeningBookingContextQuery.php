<?php

namespace App\Queries\Cinema;

use App\Enums\Booking\BookingStatus;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Screening;
use App\Models\User;

final class ScreeningBookingContextQuery
{
    public function activeHold(User $user, Screening $screening): ?Booking
    {
        return $user->bookings()
            ->where('screening_id', $screening->getKey())
            ->whereIn('status', [BookingStatus::Held->value, BookingStatus::PendingPayment->value])
            ->where('expires_at', '>', now()->utc())
            ->latest('id')
            ->first();
    }

    /** @return list<int> */
    public function seatIds(?Booking $booking): array
    {
        if ($booking === null) {
            return [];
        }

        return $booking->items()
            ->with('screeningSeat:id,seat_id')
            ->get(['id', 'screening_seat_id'])
            ->pluck('screeningSeat.seat_id')
            ->filter()
            ->map(fn ($seatId): int => (int) $seatId)
            ->values()
            ->all();
    }
}
