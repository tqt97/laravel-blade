<?php

namespace App\Actions\Booking;

use App\Enums\Booking\BookingStatus;
use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Cinema\TicketStatus;
use App\Models\Cinema\Booking;
use App\Models\Cinema\ScreeningSeat;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
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

            if ($status === BookingStatus::Confirmed) {
                throw new InvalidBookingTransition('A paid booking must be refunded before it can be cancelled.');
            }

            $booking->transitionTo(BookingStatus::Cancelled);
            $booking->expires_at = null;
            $booking->cancellation_reason = $reason;
            $booking->save();
            if (in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
                foreach ($booking->items()->lockForUpdate()->get() as $item) {
                    $seat = ScreeningSeat::query()->whereKey($item->getAttribute('screening_seat_id'))->lockForUpdate()->first();
                    if ($seat !== null && $seat->getAttribute('status') === ScreeningSeatStatus::Held) {
                        $seat->forceFill(['status' => ScreeningSeatStatus::Available, 'hold_token' => null, 'held_until' => null])->save();
                    }
                    $item->setAttribute('status', TicketStatus::Cancelled);
                    $item->save();
                }
            }

            return $booking->refresh();
        }, 3);
    }
}
