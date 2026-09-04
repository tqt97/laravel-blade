<?php

namespace App\Booking\Actions;

use App\Booking\Enums\BookingStatus;
use App\Booking\Exceptions\InvalidBookingTransition;
use App\Booking\Models\Booking;
use Illuminate\Support\Facades\DB;

final class CancelBooking
{
    public function execute(Booking $booking, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $reason): Booking {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));

            if ($status === BookingStatus::Cancelled) {
                return $booking;
            }

            if (! $status->canTransitionTo(BookingStatus::Cancelled)) {
                throw new InvalidBookingTransition('The booking cannot be cancelled from its current state.');
            }

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'expires_at' => null,
                'cancellation_reason' => $reason,
            ]);

            return $booking->refresh();
        }, 3);
    }
}
