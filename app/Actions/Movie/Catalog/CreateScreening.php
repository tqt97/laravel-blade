<?php

namespace App\Actions\Movie\Catalog;

use App\Enums\Movie\Catalog\ScreeningStatus;
use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Models\Movie\Movie;
use App\Models\Movie\Screening;
use App\Models\Movie\ScreeningRoom;
use App\Models\Movie\Seat;
use App\Support\Cinema\ScreeningConflict;
use App\Support\Time\BookingClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreateScreening
{
    /** @param array<string, int> $pricesBySeatType */
    public function execute(Movie $movie, ScreeningRoom $room, string $startsAt, string $endsAt, int $basePriceMinorUnits, string $currency = 'VND', array $pricesBySeatType = []): Screening
    {
        return DB::transaction(function () use ($movie, $room, $startsAt, $endsAt, $basePriceMinorUnits, $currency, $pricesBySeatType): Screening {
            // Lock the room before checking overlap and materializing its seats;
            // concurrent admins must not create overlapping screening inventories.
            $room = ScreeningRoom::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            $starts = CarbonImmutable::parse($startsAt, BookingClock::timezone());
            $ends = CarbonImmutable::parse($endsAt, BookingClock::timezone());

            if ($ends->lessThanOrEqualTo($starts)) {
                throw new ScreeningConflict(__('booking.messages.screening_end_before_start'));
            }

            if (Screening::query()->where('screening_room_id', $room->getKey())->scheduled()->overlapping($starts, $ends)->exists()) {
                throw new ScreeningConflict(__('booking.messages.screening_overlap'));
            }

            $screening = Screening::query()->create([
                'movie_id' => $movie->getKey(),
                'screening_room_id' => $room->getKey(),
                'starts_at' => $starts,
                'ends_at' => $ends,
                'status' => ScreeningStatus::Scheduled,
                'base_price_minor_units' => $basePriceMinorUnits,
                'currency' => strtoupper($currency),
            ]);

            $seats = Seat::query()
                ->where('screening_room_id', $room->getKey())
                ->active()
                ->get();

            foreach ($pricesBySeatType as $seatType => $price) {
                $screening->prices()->create(['seat_type' => $seatType, 'price_minor_units' => $price, 'currency' => strtoupper($currency)]);
            }

            foreach ($seats as $seat) {
                $seatType = (string) $seat->getRawOriginal('seat_type');
                $price = $pricesBySeatType[$seatType]
                    ?? ((int) $seat->getAttribute('price_minor_units') ?: $basePriceMinorUnits);

                $screening->screeningSeats()->create([
                    'seat_id' => $seat->getKey(),
                    'status' => ScreeningSeatStatus::Available,
                    'price_minor_units' => $price,
                    'currency' => strtoupper($currency),
                ]);
            }

            return $screening->load('screeningSeats');
        }, 3);
    }
}
