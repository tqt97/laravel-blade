<?php

namespace App\Booking\Actions;

use App\Booking\Enums\BookingStatus;
use App\Booking\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ExpireBooking
{
    public function execute(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            $expiresAt = $booking->getRawOriginal('expires_at') !== null
                ? CarbonImmutable::parse((string) $booking->getRawOriginal('expires_at'))
                : null;

            if ($status !== BookingStatus::Held || $expiresAt?->isFuture()) {
                return false;
            }

            return $booking->update(['status' => BookingStatus::Expired]);
        }, 3);
    }
}
