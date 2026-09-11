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

            if (! $status->isPayable() || $expiresAt?->isFuture()) {
                return false;
            }

            $payment = $booking->payment()->first();
            $paymentStatus = $payment === null
                ? null
                : PaymentStatus::tryFrom((string) $payment->getRawOriginal('status'));

            if (
                $paymentStatus?->isAwaitingProviderResolution() === true ||
                $paymentStatus === PaymentStatus::Refunding
            ) {
                return false;
            }

            app(TransitionBooking::class)->execute($booking, BookingStatus::Expired);

            $this->resourceReleaser->execute($booking);

            return true;
        }, 3);
    }
}
