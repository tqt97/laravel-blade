<?php

namespace App\Booking\Queries;

use App\Booking\Enums\BookingStatus;
use App\Booking\Models\Booking;
use App\Booking\ValueObjects\BookingPeriod;

final class BookingAvailabilityQuery
{
    public function hasConflict(int $resourceId, BookingPeriod $period): bool
    {
        return Booking::query()
            ->where('resource_id', $resourceId)
            ->where(function ($query): void {
                $query->whereIn('status', [
                    BookingStatus::PendingPayment->value,
                    BookingStatus::Confirmed->value,
                ])->orWhere(function ($query): void {
                    $query->where('status', BookingStatus::Held->value)
                        ->where('expires_at', '>', now()->utc());
                });
            })
            ->where('start_at', '<', $period->endAt)
            ->where('end_at', '>', $period->startAt)
            ->exists();
    }
}
