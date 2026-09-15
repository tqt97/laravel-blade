<?php

namespace App\Actions\Booking\Lifecycle;

use App\Enums\Booking\BookingStatus;
use App\Exceptions\Booking\InvalidBookingTransition;
use App\Models\Booking\Booking;
use Illuminate\Support\Facades\DB;

final class CancelBooking
{
    public function __construct(private readonly ReleaseBookingResources $resourceReleaser) {}

    public function execute(Booking $booking, ?string $reason = null): Booking
    {
        return DB::transaction(fn (): Booking => $this->cancelLocked($booking, $reason), 3);
    }

    /** The caller must hold the surrounding transaction. */
    public function cancelLocked(Booking $booking, ?string $reason = null): Booking
    {
        $operation = function () use ($booking, $reason): Booking {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));

            if ($status === BookingStatus::Cancelled) {
                return $booking;
            }

            if ($status === BookingStatus::Confirmed) {
                throw new InvalidBookingTransition(__('booking.messages.paid_booking_refund_first'));
            }

            $booking->cancellation_reason = $reason;
            $booking->expires_at = null;
            app(TransitionBooking::class)->execute($booking, BookingStatus::Cancelled, $reason);

            if ($status->isPayable()) {
                $this->resourceReleaser->execute($booking);
            }

            return $booking->refresh();
        };

        return $operation();
    }
}
