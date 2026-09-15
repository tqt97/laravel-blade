<?php

namespace App\Actions\Booking\Payment;

use App\Actions\Booking\Checkout\PayBooking;
use App\Actions\Booking\Lifecycle\ExpireBooking;
use App\DTO\Booking\PayBookingData;
use App\Exceptions\Booking\BookingExpired;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;

final class ProcessBookingPayment
{
    public function __construct(
        private readonly PayBooking $payBooking,
        private readonly ExpireBooking $expireBooking,
    ) {}

    public function execute(Booking $booking, PayBookingData $data): Payment
    {
        try {
            return $this->payBooking->execute($booking, $data->paymentMethodId, $data->quantities);
        } catch (BookingExpired $exception) {
            $this->expireBooking->execute($booking);

            throw $exception;
        }
    }
}
