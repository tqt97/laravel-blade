<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Movie\Booking;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use Illuminate\Support\Facades\DB;

final class CancelBooking
{
    public function __construct(private readonly ReleaseBookingResources $resourceReleaser) {}

    public function execute(Booking $booking, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $reason): Booking {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));

            if ($status === BookingStatus::Cancelled) {
                return $booking;
            }

            if ($status === BookingStatus::Confirmed) {
                throw new InvalidBookingTransition(__('booking.messages.paid_booking_refund_first'));
            }

            $booking->transitionTo(BookingStatus::Cancelled);
            $booking->expires_at = null;
            $booking->cancellation_reason = $reason;
            $booking->save();

            if (in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
                $this->resourceReleaser->execute($booking);
            }

            return $booking->refresh();
        }, 3);
    }
}
