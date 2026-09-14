<?php

namespace App\Actions\Booking\Lifecycle;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Support\Booking\BookingClock;
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

            // The hold TTL is the hard resource boundary. A late provider
            // success is handled by FinalizeSuccessfulPayment as a refund,
            // rather than keeping seats locked indefinitely.
            if ($booking->payment()->refunding()->exists()) {
                return false;
            }

            app(TransitionBooking::class)->execute($booking, BookingStatus::Expired);

            $this->resourceReleaser->execute($booking);

            return true;
        }, 3);
    }
}
