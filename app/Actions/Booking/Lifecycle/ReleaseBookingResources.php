<?php

namespace App\Actions\Booking\Lifecycle;

use App\Enums\Catalog\Seating\ScreeningSeatStatus;
use App\Enums\Commerce\CouponReservationStatus;
use App\Enums\Inventory\InventoryMovementType;
use App\Enums\Inventory\InventoryStockMode;
use App\Enums\Ticketing\TicketStatus;
use App\Models\Booking\Booking;
use App\Models\Booking\ScreeningSeat;
use App\Models\Commerce\Concession;
use App\Models\Commerce\Coupon;
use App\Models\Commerce\CouponReservation;
use App\Models\Commerce\CouponUserUsage;
use App\Models\Inventory\InventoryMovement;

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

            if (
                $seat !== null &&
                $seat->getAttribute('status') === ScreeningSeatStatus::Held &&
                (int) $seat->getAttribute('held_by_booking_id') === $booking->getKey()
            ) {
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

            if (
                $concession !== null &&
                $concession->getAttribute('stock') !== null &&
                ! InventoryMovement::query()->where('idempotency_key', $idempotencyKey)->exists()
            ) {
                $stockBefore = (int) $concession->stock;
                $concession->increment('stock', (int) $line->getAttribute('quantity'));

                InventoryMovement::query()->firstOrCreate([
                    'idempotency_key' => 'booking-release-'.$booking->getKey().'-'.$line->getAttribute('concession_id'),
                ], [
                    'concession_id' => $concession->getKey(),
                    'booking_id' => $booking->getKey(),
                    'type' => InventoryMovementType::Release,
                    'stock_mode' => InventoryStockMode::Finite,
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
            $coupon = Coupon::query()
                ->whereKey($reservation->coupon_id)
                ->lockForUpdate()
                ->first();

            if ($coupon !== null) {
                if ($coupon->used_count > 0) {
                    $coupon->decrement('used_count');
                }
                if ($coupon->reserved_count > 0) {
                    $coupon->decrement('reserved_count');
                }
            }

            $reservation->update(['status' => CouponReservationStatus::Released]);
            CouponUserUsage::query()
                ->where('coupon_id', $reservation->coupon_id)
                ->where('user_id', $booking->user_id)
                ->where('booking_id', $booking->getKey())
                ->where('status', CouponReservationStatus::Reserved)
                ->update(['status' => CouponReservationStatus::Released]);
        }
    }
}
