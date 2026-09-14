<?php

namespace App\Queries\Booking;

use App\Models\Booking\Booking;
use App\Models\Booking\ScreeningSeat;
use App\Models\Catalog\Screening;
use App\Models\User;

final class ScreeningBookingContextQuery
{
    public function activeHold(User $user, Screening $screening): ?Booking
    {
        return Booking::query()
            ->ownedBy($user->id)
            ->where('screening_id', $screening->getKey())
            ->activeHold()
            ->latest('id')
            ->first();
    }

    /** @return list<int> */
    public function ownedSeatIds(User $user, Screening $screening): array
    {
        $booking = $this->activeHold($user, $screening);

        if ($booking === null) {
            return [];
        }

        return ScreeningSeat::query()
            ->where('screening_id', $screening->getKey())
            ->where('held_by_booking_id', $booking->getKey())
            ->pluck('seat_id')
            ->map(static fn (int|string $seatId): int => (int) $seatId)
            ->values()
            ->all();
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
