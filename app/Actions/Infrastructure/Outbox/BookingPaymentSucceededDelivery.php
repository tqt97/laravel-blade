<?php

namespace App\Actions\Infrastructure\Outbox;

use App\Contracts\OutboxDeliveryHandler;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking\Booking;
use App\Models\User;
use App\Notifications\BookingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class BookingPaymentSucceededDelivery implements OutboxDeliveryHandler
{
    public function execute(User $user, Booking $booking): void
    {
        DB::transaction(function () use ($user, $booking): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $this->notifyOnce($lockedUser, $booking, 'booking_confirmed');
        }, 3);
        Mail::to($user)->send(new BookingConfirmationMail($booking));
    }

    private function notifyOnce(User $user, Booking $booking, string $event): void
    {
        $key = $event.':'.$booking->getKey();
        if ($user->notifications()->where('data->key', $key)->exists()) {
            return;
        }

        $user->notify(new BookingNotification($booking, $event));
    }
}
