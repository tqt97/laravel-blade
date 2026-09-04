<?php

namespace App\Booking\Actions;

use App\Booking\Enums\BookingStatus;
use App\Booking\Exceptions\BookingExpired;
use App\Booking\Exceptions\InvalidBookingTransition;
use App\Booking\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ConfirmBooking
{
    public function execute(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            $expiresAt = $booking->getRawOriginal('expires_at') !== null
                ? CarbonImmutable::parse((string) $booking->getRawOriginal('expires_at'))
                : null;

            if ($status === BookingStatus::Confirmed) {
                return $booking;
            }

            if ($status === BookingStatus::Held && $expiresAt?->isPast()) {
                $booking->update(['status' => BookingStatus::Expired]);
                throw new BookingExpired('The booking hold has expired.');
            }

            if (! $status->canTransitionTo(BookingStatus::Confirmed)) {
                throw new InvalidBookingTransition('The booking cannot be confirmed from its current state.');
            }

            $booking->update(['status' => BookingStatus::Confirmed, 'expires_at' => null]);

            return $booking->refresh();
        }, 3);
    }
}
