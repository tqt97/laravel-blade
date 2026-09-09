<?php

namespace App\Queries\Movie;

use App\Models\Movie\Booking;
use App\Models\Movie\Screening;
use App\Models\User;

final class ScreeningBookingContextQuery
{
    public function activeHold(User $user, Screening $screening): ?Booking
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->where('screening_id', $screening->getKey())
            ->activeHold()
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
