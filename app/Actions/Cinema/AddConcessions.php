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
            if (! in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
                throw new RuntimeException('Combos can only be added before payment succeeds.');
            }
            $addedTotal = 0;
            foreach ($quantitiesByConcession as $concessionId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity < 1) {
                    continue;
                }
                $concession = Concession::query()->whereKey($concessionId)->where('is_active', true)->lockForUpdate()->firstOrFail();
                $stock = $concession->getAttribute('stock');
                if ($stock !== null && $stock < $quantity) {
                    throw new RuntimeException('A selected combo does not have enough stock.');
                }
                $unit = (int) $concession->getAttribute('price_minor_units');
                $total = $unit * $quantity;
                $line = $booking->concessions()->where('concession_id', $concession->getKey())->first();
                if ($line === null) {
                    $booking->concessions()->create(['concession_id' => $concession->getKey(), 'quantity' => $quantity, 'unit_price_minor_units' => $unit, 'total_minor_units' => $total, 'currency' => $concession->getAttribute('currency')]);
                } else {
                    $line->increment('quantity', $quantity);
                    $line->increment('total_minor_units', $total);
                }
                if ($stock !== null) {
                    $concession->decrement('stock', $quantity);
                }
                $addedTotal += $total;
            }
            $booking->increment('subtotal_minor_units', $addedTotal);
            $booking->increment('total_minor_units', $addedTotal);
            $booking->increment('amount_minor_units', $addedTotal);

            return $booking->refresh();
        }, 3);
    }
}
