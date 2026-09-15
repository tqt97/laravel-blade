<?php

namespace App\Queries\Catalog;

use App\Models\Booking\ScreeningSeat;
use App\Models\Catalog\Screening;
use App\Models\User;
use App\Queries\Booking\ScreeningBookingContextQuery;
use App\Queries\Commerce\AvailableConcessionsQuery;

final class ScreeningAvailabilityQuery
{
    public function __construct(
        private readonly ScreeningBookingContextQuery $bookingContext,
        private readonly AvailableConcessionsQuery $concessions,
    ) {}

    /** @return array{seats: array<int, array{available: bool, owned_by_current_booking: bool}>, concessions: array<string, array{stock: int|null, selected: int, max: int}>} */
    public function execute(Screening $screening, ?User $user = null): array
    {
        $activeHold = $user === null ? null : $this->bookingContext->activeHold($user, $screening);
        $ownedSeatIds = $user === null
            ? []
            : array_map('strval', $this->bookingContext->ownedSeatIds($user, $screening));
        $selectedQuantities = $activeHold?->concessions()->pluck('quantity', 'concession_id')->all() ?? [];

        $seats = ScreeningSeat::query()
            ->where('screening_id', $screening->getKey())
            ->get(['seat_id', 'status', 'held_until'])
            ->mapWithKeys(function (ScreeningSeat $seat) use ($ownedSeatIds): array {
                return [(string) $seat->seat_id => [
                    'available' => $seat->isAvailableForSelection(),
                    'owned_by_current_booking' => in_array((string) $seat->seat_id, $ownedSeatIds, true),
                ]];
            })
            ->all();

        return [
            'seats' => $seats,
            'concessions' => $this->concessions->availabilityForCurrency((string) $screening->currency, $selectedQuantities),
        ];
    }
}
