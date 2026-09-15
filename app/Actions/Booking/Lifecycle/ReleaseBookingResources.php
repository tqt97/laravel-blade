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
        $items = $booking->items()->lockForUpdate()->get();
        $seats = ScreeningSeat::query()
            ->whereIn('id', $items->pluck('screening_seat_id')->unique()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $seat = $seats->get($item->getAttribute('screening_seat_id'));

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

        $lines = $booking->concessions()->lockForUpdate()->get();
        $concessions = Concession::query()
            ->whereIn('id', $lines->pluck('concession_id')->unique()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $movementKeys = $lines->mapWithKeys(fn ($line): array => [
            'booking-release-'.$booking->getKey().'-'.$line->getAttribute('concession_id') => true,
        ]);

        $existingMovementKeys = InventoryMovement::query()
            ->whereIn('idempotency_key', $movementKeys->keys())
            ->pluck('idempotency_key')
            ->flip();

        foreach ($lines as $line) {
            $concession = $concessions->get($line->getAttribute('concession_id'));

            $idempotencyKey = 'booking-release-'.$booking->getKey().'-'.$line->getAttribute('concession_id');

            if (
                $concession !== null &&
                $concession->getAttribute('stock') !== null &&
                ! $existingMovementKeys->has($idempotencyKey)
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
