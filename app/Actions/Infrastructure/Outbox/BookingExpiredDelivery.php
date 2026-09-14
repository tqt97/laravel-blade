<?php

namespace App\Actions\Infrastructure\Outbox;

use App\Contracts\OutboxDeliveryHandler;
use App\Models\Booking\Booking;
use App\Models\User;
use App\Notifications\BookingNotification;

final class BookingExpiredDelivery implements OutboxDeliveryHandler
{
    public function execute(User $user, Booking $booking): void
    {
        $key = 'booking_expired:'.$booking->getKey();
        if ($user->notifications()->where('data->key', $key)->exists()) {
            return;
        }

        $user->notify(new BookingNotification($booking, 'booking_expired'));
    }
}
