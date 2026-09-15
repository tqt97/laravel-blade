<?php

namespace App\Policies;

use App\Models\Booking\BookingItem;
use App\Models\User;

class BookingItemPolicy
{
    public function view(User $user, BookingItem $bookingItem): bool
    {
        return (int) $bookingItem->booking?->getAttribute('user_id') === (int) $user->getKey();
    }
}
