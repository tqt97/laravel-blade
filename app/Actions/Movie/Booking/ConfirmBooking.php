<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Movie\Booking;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use App\Support\Time\BookingClock;
use Illuminate\Support\Facades\DB;

final class ConfirmBooking
{
    public function execute(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            $expiresAt = $booking->getRawOriginal('expires_at') !== null
                ? BookingClock::parseStored((string) $booking->getRawOriginal('expires_at'))
                : null;

            if ($status === BookingStatus::Confirmed) {
                return $booking;
            }

            $payment = $booking->payment()->lockForUpdate()->first();

            if ($payment === null
                || $payment->getRawOriginal('status') !== PaymentStatus::Succeeded->value
                || blank($payment->getRawOriginal('provider_payment_id'))) {
                throw new BookingOperationFailed(__('booking.messages.payment_not_confirmed'));
            }

            if ($status === BookingStatus::Held && $expiresAt?->lessThanOrEqualTo(BookingClock::now())) {
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
