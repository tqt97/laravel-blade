<?php

namespace App\Actions\Cinema;

use App\Enums\Booking\BookingStatus;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Concession;
use App\Models\Cinema\ConcessionInventoryMovement;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Money\Money;
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
        $totalDelta = 0;
        foreach ($quantitiesByConcession as $concessionId => $quantity) {
            $desiredQuantity = max(0, (int) $quantity);
            $concession = Concession::query()->whereKey($concessionId)->where('is_active', true)->lockForUpdate()->firstOrFail();
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
                $booking->concessions()->create(['concession_id' => $concession->getKey(), 'quantity' => $desiredQuantity, 'unit_price_minor_units' => $unit, 'total_minor_units' => $newTotal, 'currency' => strtoupper((string) $concession->getAttribute('currency'))]);
            } else {
                $line->forceFill(['quantity' => $desiredQuantity, 'total_minor_units' => $newTotal])->save();
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
                'type' => $quantityDelta > 0 ? 'sale_reserve' : 'release',
                'quantity_delta' => -$quantityDelta,
                'stock_before' => $stockBefore,
                'stock_after' => $stock === null ? null : (int) $concession->fresh()->stock,
                'reference' => 'booking-'.$booking->getKey(),
            ]);

            $totalDelta += $newTotal - $currentTotal;
        }
        $bookingMoney = Money::fromMinorUnits((int) $booking->amount_minor_units, strtoupper((string) $booking->currency));
        $bookingTotal = $totalDelta >= 0
            ? $bookingMoney->add(Money::fromMinorUnits($totalDelta, $bookingMoney->currency))
            : Money::fromMinorUnits($bookingMoney->minorUnits + $totalDelta, $bookingMoney->currency);
        $booking->forceFill([
            'subtotal_minor_units' => (int) $booking->getAttribute('subtotal_minor_units') + $totalDelta,
            'total_minor_units' => (int) $booking->getAttribute('total_minor_units') + $totalDelta,
            'amount_minor_units' => $bookingTotal->minorUnits,
        ])->save();

        return $booking->refresh();
    }
}
