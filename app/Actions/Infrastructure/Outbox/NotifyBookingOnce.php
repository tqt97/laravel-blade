<?php

namespace App\Actions\Infrastructure\Outbox;

use App\Models\Booking\Booking;
use App\Models\User;
use App\Notifications\BookingNotification;
use Illuminate\Support\Facades\DB;

final class NotifyBookingOnce
{
    public function execute(User $user, Booking $booking, string $event): void
    {
        DB::transaction(function () use ($user, $booking, $event): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $key = $event.':'.$booking->getKey();

            if ($lockedUser->notifications()->where('data->key', $key)->exists()) {
                return;
            }

            $lockedUser->notify(new BookingNotification($booking, $event));
        }, 3);
    }
}
