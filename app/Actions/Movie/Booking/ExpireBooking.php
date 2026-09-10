<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Movie\Booking;
use App\Support\Time\BookingClock;
use Illuminate\Support\Facades\DB;

final class ExpireBooking
{
    public function __construct(private readonly ReleaseBookingResources $resourceReleaser) {}

    public function execute(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            $expiresAt = $booking->getRawOriginal('expires_at') !== null
                ? BookingClock::parseStored((string) $booking->getRawOriginal('expires_at'))
                : null;

            if (! in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true) || $expiresAt?->isFuture()) {
                return false;
            }

            $payment = $booking->payment()->first();
            $paymentStatus = $payment === null
                ? null
                : PaymentStatus::tryFrom((string) $payment->getRawOriginal('status'));
            if (in_array($paymentStatus, [PaymentStatus::Processing, PaymentStatus::RequiresAction, PaymentStatus::Pending, PaymentStatus::Unknown, PaymentStatus::Refunding], true)) {
                return false;
            }

            $booking->transitionTo(BookingStatus::Expired);
            $booking->save();

            $this->resourceReleaser->execute($booking);

            return true;
        }, 3);
    }
}
