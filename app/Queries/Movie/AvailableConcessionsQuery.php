<?php

namespace App\Queries\Movie;

use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use Illuminate\Database\Eloquent\Collection;

final class AvailableConcessionsQuery
{
    /** @return Collection<int, Concession> */
    public function get(string $currency): Collection
    {
        return Concession::query()
            ->availableForBooking($currency)
            ->select(['id', 'name', 'image_url', 'price_minor_units', 'currency', 'stock'])
            ->orderBy('name')
            ->limit((int) config('booking.listing.concessions_per_page'))
            ->get();
    }

    /** @return array<string, array{stock: int|null, selected: int, max: int}> */
    public function availability(Booking $booking): array
    {
        $selectedQuantities = $booking->concessions()->pluck('quantity', 'concession_id');
        $currency = strtoupper((string) ($booking->pricing_currency ?? $booking->currency));
        $maxQuantity = (int) config('booking.limits.max_combo_quantity');
        $availability = [];

        $concessions = Concession::query()
            ->availableForBooking($currency)
            ->select(['id', 'stock'])
            ->orderBy('name')
            ->get();

        foreach ($concessions as $concession) {
            $selected = (int) ($selectedQuantities[$concession->getKey()] ?? 0);
            $stock = $concession->stock;
            $availability[(string) $concession->getKey()] = [
                'stock' => $stock,
                'selected' => $selected,
                'max' => $stock === null ? $maxQuantity : min($maxQuantity, $selected + $stock),
            ];
        }

        return $availability;
    }
}
