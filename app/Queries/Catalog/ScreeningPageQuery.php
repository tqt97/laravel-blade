<?php

namespace App\Queries\Catalog;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Booking\ScreeningSeat;
use App\Models\Catalog\Screening;
use App\Models\Commerce\Concession;
use App\Models\User;
use App\Queries\Booking\ScreeningBookingContextQuery;
use App\Queries\Commerce\AvailableConcessionsQuery;
use Illuminate\Database\Eloquent\Collection;

final class ScreeningPageQuery
{
    public function __construct(
        private readonly ScreeningBookingContextQuery $bookingContext,
        private readonly AvailableConcessionsQuery $concessions,
    ) {}

    /** @return array{seatSummary: array{available:int, total:int}, activeHold: ?Booking, activeHoldSeatIds: list<int>, concessions: Collection<int, Concession>} */
    public function execute(Screening $screening, ?User $user = null): array
    {
        $screening->load(['movie', 'room', 'screeningSeats.seat']);

        $activeHold = null;
        $activeHoldSeatIds = [];

        if ($user !== null) {
            $activeHold = $this->bookingContext->activeHold($user, $screening);
            $activeHold?->load(['concessions', 'items.screeningSeat.seat']);
            $activeHoldSeatIds = $activeHold?->getRawOriginal('status') === BookingStatus::Held->value
                ? $this->bookingContext->seatIds($activeHold)
                : [];
        }

        return [
            'seatSummary' => $this->seatSummary($screening),
            'activeHold' => $activeHold,
            'activeHoldSeatIds' => $activeHoldSeatIds,
            'concessions' => $this->concessions->get((string) $screening->currency),
        ];
    }

    /** @return array{available:int, total:int} */
    private function seatSummary(Screening $screening): array
    {
        $seats = $screening->screeningSeats;

        return [
            'available' => $seats->filter(
                fn (ScreeningSeat $seat): bool => $seat->isAvailableForSelection(),
            )->count(),
            'total' => $seats->count(),
        ];
    }
}
