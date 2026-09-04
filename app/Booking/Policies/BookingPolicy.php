<?php

namespace App\Booking\Policies;

use App\Booking\Models\Booking;
use App\Models\User;

final class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->is_admin || $booking->user_id === $user->id;
    }

    public function confirm(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->is_admin || $booking->user_id === $user->id;
    }
}
