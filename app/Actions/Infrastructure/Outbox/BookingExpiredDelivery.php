<?php

namespace App\Actions\Infrastructure\Outbox;

use App\Contracts\OutboxDeliveryHandler;
use App\Models\Movie\Booking;
use App\Models\User;
use App\Notifications\MovieBookingNotification;

final class BookingExpiredDelivery implements OutboxDeliveryHandler
{
    public function execute(User $user, Booking $booking): void
    {
        $key = 'booking_expired:'.$booking->getKey();
        if ($user->notifications()->where('data->key', $key)->exists()) {
            return;
        }

        $user->notify(new MovieBookingNotification($booking, 'booking_expired'));
    }
}
