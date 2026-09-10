<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Inventory\InventoryMovementType;
use App\Enums\Movie\Booking\CouponReservationStatus;
use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Models\Inventory\InventoryMovement;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Models\Movie\Coupon;
use App\Models\Movie\CouponReservation;
use App\Models\Movie\ScreeningSeat;

final class ReleaseBookingResources
{
    /**
     * Release seats, cancel provisional tickets and return combo stock.
     * The caller must already be inside a database transaction.
     */
    public function execute(Booking $booking): void
    {
        foreach ($booking->items()->lockForUpdate()->get() as $item) {
            $seat = ScreeningSeat::query()
                ->whereKey($item->getAttribute('screening_seat_id'))
                ->lockForUpdate()
                ->first();

            if ($seat !== null && $seat->getAttribute('status') === ScreeningSeatStatus::Held && (int) $seat->getAttribute('held_by_booking_id') === $booking->getKey()) {
                $seat->forceFill([
                    'status' => ScreeningSeatStatus::Available,
                    'hold_token' => null,
                    'held_by_booking_id' => null,
                    'held_until' => null,
                ])->save();
            }

            if ($item->getAttribute('status') !== TicketStatus::Cancelled) {
                $item->setAttribute('status', TicketStatus::Cancelled);
                $item->save();
            }
        }

        foreach ($booking->concessions()->lockForUpdate()->get() as $line) {
            $concession = Concession::query()
                ->whereKey($line->getAttribute('concession_id'))
                ->lockForUpdate()
                ->first();

            $idempotencyKey = 'booking-release-'.$booking->getKey().'-'.$line->getAttribute('concession_id');
            if ($concession !== null && $concession->getAttribute('stock') !== null && ! InventoryMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                $stockBefore = (int) $concession->stock;
                $concession->increment('stock', (int) $line->getAttribute('quantity'));
                InventoryMovement::query()->firstOrCreate([
                    'idempotency_key' => 'booking-release-'.$booking->getKey().'-'.$line->getAttribute('concession_id'),
                ], [
                    'concession_id' => $concession->getKey(),
                    'booking_id' => $booking->getKey(),
                    'type' => InventoryMovementType::Release,
                    'quantity_delta' => (int) $line->getAttribute('quantity'),
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore + (int) $line->getAttribute('quantity'),
                    'reference' => 'booking-'.$booking->getKey(),
                    'idempotency_key' => $idempotencyKey,
                ]);
            }
        }

        $reservation = CouponReservation::query()
            ->where('booking_id', $booking->getKey())
            ->where('status', CouponReservationStatus::Reserved)
            ->lockForUpdate()
            ->first();
        if ($reservation !== null) {
            $coupon = Coupon::query()->whereKey($reservation->coupon_id)->lockForUpdate()->first();
            if ($coupon !== null && $coupon->used_count > 0) {
                $coupon->decrement('used_count');
            }
            $reservation->update(['status' => CouponReservationStatus::Released]);
        }
    }
}
