<?php

namespace App\Actions\Booking;

use App\Enums\Booking\BookingStatus;
use App\Models\Cinema\Booking;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
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
                ? CarbonImmutable::parse((string) $booking->getRawOriginal('expires_at'), 'UTC')
                : null;

            if ($status === BookingStatus::Confirmed) {
                return $booking;
            }

            if ($status === BookingStatus::Held && $expiresAt?->lessThanOrEqualTo(now()->utc())) {
                $booking->transitionTo(BookingStatus::Expired);
                $booking->save();

                return $booking->refresh();
            }

            if (! $status->canTransitionTo(BookingStatus::Confirmed)) {
                throw new InvalidBookingTransition(__('booking.messages.invalid_transition'));
            }

            $booking->transitionTo(BookingStatus::Confirmed);
            $booking->expires_at = null;
            $booking->save();

            return $booking->refresh();
        }, 3);
    }
}
