<?php

namespace App\Actions\Booking;

use App\Enums\Booking\BookingStatus;
use App\Models\Cinema\Booking;
use Carbon\CarbonImmutable;
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
                ? CarbonImmutable::parse((string) $booking->getRawOriginal('expires_at'), 'UTC')
                : null;

            if (! in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true) || $expiresAt?->isFuture()) {
                return false;
            }

            $booking->transitionTo(BookingStatus::Expired);
            $booking->save();

            $this->resourceReleaser->execute($booking);

            return true;
        }, 3);
    }
}
