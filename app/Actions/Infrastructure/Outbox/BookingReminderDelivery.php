<?php

namespace App\Actions\Infrastructure\Outbox;

use App\Contracts\OutboxDeliveryHandler;
use App\Mail\BookingReminderMail;
use App\Models\Movie\Booking;
use App\Models\User;
use App\Notifications\MovieBookingNotification;
use Illuminate\Support\Facades\Mail;

final class BookingReminderDelivery implements OutboxDeliveryHandler
{
    public function execute(User $user, Booking $booking): void
    {
        $this->notifyOnce($user, $booking, 'booking_reminder');
        Mail::to($user)->send(new BookingReminderMail($booking));
    }

    private function notifyOnce(User $user, Booking $booking, string $event): void
    {
        $key = $event.':'.$booking->getKey();
        if ($user->notifications()->where('data->key', $key)->exists()) {
            return;
        }

        $user->notify(new MovieBookingNotification($booking, $event));
    }
}
