<?php

namespace App\Actions\Cinema;

use App\Enums\Booking\BookingStatus;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Concession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AddConcessions
{
    /** @param array<int, int> $quantitiesByConcession */
    public function execute(Booking $booking, array $quantitiesByConcession): Booking
    {
        ksort($quantitiesByConcession);

        return DB::transaction(function () use ($booking, $quantitiesByConcession): Booking {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            if ($status !== BookingStatus::Held) {
                throw new RuntimeException('Combos cannot be changed after payment has started.');
            }
            $totalDelta = 0;
            foreach ($quantitiesByConcession as $concessionId => $quantity) {
                $desiredQuantity = max(0, (int) $quantity);
                $concession = Concession::query()->whereKey($concessionId)->where('is_active', true)->lockForUpdate()->firstOrFail();
                $line = $booking->concessions()->where('concession_id', $concession->getKey())->lockForUpdate()->first();
                $currentQuantity = (int) ($line?->getAttribute('quantity') ?? 0);
                $currentTotal = (int) ($line?->getAttribute('total_minor_units') ?? 0);
                $unit = (int) ($line?->getAttribute('unit_price_minor_units') ?? $concession->getAttribute('price_minor_units'));
                $quantityDelta = $desiredQuantity - $currentQuantity;

                if ($quantityDelta === 0) {
                    continue;
                }

                $stock = $concession->getAttribute('stock');
                if ($quantityDelta > 0 && $stock !== null && $stock < $quantityDelta) {
                    throw new RuntimeException('A selected combo does not have enough stock.');
                }

                $newTotal = $unit * $desiredQuantity;
                if ($desiredQuantity === 0) {
                    $line?->delete();
                } elseif ($line === null) {
                    $booking->concessions()->create(['concession_id' => $concession->getKey(), 'quantity' => $desiredQuantity, 'unit_price_minor_units' => $unit, 'total_minor_units' => $newTotal, 'currency' => $concession->getAttribute('currency')]);
                } else {
                    $line->forceFill(['quantity' => $desiredQuantity, 'total_minor_units' => $newTotal])->save();
                }

                if ($stock !== null && $quantityDelta > 0) {
                    $concession->decrement('stock', $quantityDelta);
                } elseif ($stock !== null) {
                    $concession->increment('stock', -$quantityDelta);
                }

                $totalDelta += $newTotal - $currentTotal;
            }
            $booking->forceFill([
                'subtotal_minor_units' => (int) $booking->getAttribute('subtotal_minor_units') + $totalDelta,
                'total_minor_units' => (int) $booking->getAttribute('total_minor_units') + $totalDelta,
                'amount_minor_units' => (int) $booking->getAttribute('amount_minor_units') + $totalDelta,
            ])->save();

            return $booking->refresh();
        }, 3);
    }
}
