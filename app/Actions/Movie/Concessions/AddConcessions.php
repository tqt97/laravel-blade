<?php

namespace App\Actions\Movie\Concessions;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Booking\CouponType;
use App\Enums\Movie\Concessions\InventoryMovementType;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Models\Movie\ConcessionInventoryMovement;
use App\Models\Movie\Coupon;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Support\Facades\DB;

final class AddConcessions
{
    /** @param array<int, int> $quantitiesByConcession */
    public function execute(Booking $booking, array $quantitiesByConcession): Booking
    {
        ksort($quantitiesByConcession);

        return DB::transaction(function () use ($booking, $quantitiesByConcession): Booking {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            return $this->executeForLockedBooking($booking, $quantitiesByConcession);
        }, 3);
    }

    /**
     * Sync combo quantities while the caller owns the booking row lock.
     *
     * This is used by the payment transaction so stock validation, booking
     * totals, and the payment claim commit or roll back together.
     *
     * @param  array<int, int>  $quantitiesByConcession
     */
    public function executeForLockedBooking(Booking $booking, array $quantitiesByConcession): Booking
    {
        ksort($quantitiesByConcession);

        $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
        if ($status !== BookingStatus::Held) {
            throw new BookingOperationFailed(__('booking.messages.combos_locked'));
        }

        $ticketCount = $booking->items()->count();
        $maxComboCount = $ticketCount * (int) config('booking.limits.max_combos_per_ticket');
        $currentQuantities = $booking->concessions()
            ->pluck('quantity', 'concession_id')
            ->map(static fn ($quantity): int => (int) $quantity);
        $requestedComboCount = $currentQuantities->sum();

        foreach ($quantitiesByConcession as $concessionId => $quantity) {
            $requestedComboCount -= (int) ($currentQuantities[$concessionId] ?? 0);
            $requestedComboCount += max(0, (int) $quantity);
        }

        if ($requestedComboCount > $maxComboCount) {
            throw new BookingOperationFailed(__('booking.messages.combo_limit_per_seat'));
        }

        $totalDelta = 0;
        foreach ($quantitiesByConcession as $concessionId => $quantity) {
            $desiredQuantity = max(0, (int) $quantity);
            $concession = Concession::query()->whereKey($concessionId)->active()->lockForUpdate()->first();
            if ($concession === null) {
                throw new BookingOperationFailed(__('booking.messages.combo_unavailable'));
            }

            if (strtoupper((string) $booking->currency) !== strtoupper((string) $concession->currency)) {
                throw new BookingOperationFailed(__('booking.messages.currency_mismatch'));
            }

            $line = $booking->concessions()->where('concession_id', $concession->getKey())->lockForUpdate()->first();

            if ($line !== null && strtoupper((string) $line->currency) !== strtoupper((string) $booking->currency)) {
                throw new BookingOperationFailed(__('booking.messages.currency_mismatch'));
            }

            $currentQuantity = (int) ($line?->getAttribute('quantity') ?? 0);
            $currentTotal = (int) ($line?->getAttribute('total_minor_units') ?? 0);
            $unit = (int) ($line?->getAttribute('unit_price_minor_units') ?? $concession->getAttribute('price_minor_units'));
            $quantityDelta = $desiredQuantity - $currentQuantity;

            if ($quantityDelta === 0) {
                continue;
            }

            $stock = $concession->getAttribute('stock');
            if ($quantityDelta > 0 && $stock !== null && $stock < $quantityDelta) {
                throw new BookingOperationFailed(__('booking.messages.combo_stock_unavailable'));
            }

            $newTotal = $unit * $desiredQuantity;
            if ($desiredQuantity === 0) {
                $line?->delete();
            } elseif ($line === null) {
                $booking->concessions()->create([
                    'concession_id' => $concession->getKey(),
                    'quantity' => $desiredQuantity,
                    'unit_price_minor_units' => $unit,
                    'total_minor_units' => $newTotal,
                    'currency' => strtoupper((string) $concession->getAttribute('currency')),
                ]);
            } else {
                $line->forceFill([
                    'quantity' => $desiredQuantity,
                    'total_minor_units' => $newTotal,
                ])->save();
            }

            $stockBefore = $stock;
            if ($stock !== null && $quantityDelta > 0) {
                $concession->decrement('stock', $quantityDelta);
            } elseif ($stock !== null) {
                $concession->increment('stock', -$quantityDelta);
            }

            ConcessionInventoryMovement::query()->create([
                'concession_id' => $concession->getKey(),
                'booking_id' => $booking->getKey(),
                'type' => $quantityDelta > 0 ? InventoryMovementType::SaleReserve : InventoryMovementType::Release,
                'quantity_delta' => -$quantityDelta,
                'stock_before' => $stockBefore,
                'stock_after' => $stock === null ? null : (int) $concession->fresh()->stock,
                'reference' => 'booking-'.$booking->getKey(),
            ]);

            $totalDelta += $newTotal - $currentTotal;
        }
        $newSubtotal = (int) $booking->getAttribute('subtotal_minor_units') + $totalDelta;
        $discount = (int) $booking->getAttribute('discount_minor_units');
        if ($booking->coupon_id !== null) {
            $coupon = Coupon::query()->whereKey($booking->coupon_id)->first();
            if ($coupon !== null) {
                $couponType = CouponType::from((string) $coupon->getRawOriginal('type'));
                $couponValue = (int) $coupon->getAttribute('value');
                $discount = $couponType === CouponType::Percentage
                    ? intdiv($newSubtotal * min(100, $couponValue), 100)
                    : $couponValue;
                if ($coupon->maximum_discount_minor_units !== null) {
                    $discount = min($discount, $coupon->maximum_discount_minor_units);
                }
                $discount = min($discount, $newSubtotal);
            }
        }
        $booking->forceFill([
            'subtotal_minor_units' => $newSubtotal,
            'discount_minor_units' => $discount,
            'total_minor_units' => $newSubtotal - $discount,
            'amount_minor_units' => $newSubtotal - $discount,
        ])->save();

        return $booking->refresh();
    }
}
