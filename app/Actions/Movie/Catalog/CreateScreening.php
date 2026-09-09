<?php

namespace App\Actions\Movie\Catalog;

use App\Enums\Movie\Catalog\ScreeningStatus;
use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Models\Movie\Movie;
use App\Models\Movie\Screening;
use App\Models\Movie\ScreeningRoom;
use App\Models\Movie\Seat;
use App\Support\Cinema\ScreeningConflict;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreateScreening
{
    /** @param array<string, int> $pricesBySeatType */
    public function execute(Movie $movie, ScreeningRoom $room, string $startsAt, string $endsAt, int $basePriceMinorUnits, string $currency = 'VND', array $pricesBySeatType = []): Screening
    {
        return DB::transaction(function () use ($movie, $room, $startsAt, $endsAt, $basePriceMinorUnits, $currency, $pricesBySeatType): Screening {
            $room = ScreeningRoom::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            $starts = CarbonImmutable::parse($startsAt, $room->getAttribute('timezone'))->utc();
            $ends = CarbonImmutable::parse($endsAt, $room->getAttribute('timezone'))->utc();

            if ($ends->lessThanOrEqualTo($starts)) {
                throw new ScreeningConflict('The screening end must be after its start.');
            }

            if (Screening::query()->where('screening_room_id', $room->getKey())->scheduled()->overlapping($starts, $ends)->exists()) {
                throw new ScreeningConflict('The screening overlaps another screening in this room.');
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

            $seats = Seat::query()->where('screening_room_id', $room->getKey())->active()->get();
            foreach ($pricesBySeatType as $seatType => $price) {
                $screening->prices()->create(['seat_type' => $seatType, 'price_minor_units' => $price, 'currency' => strtoupper($currency)]);
            }

            foreach ($seats as $seat) {
                $seatType = (string) $seat->getRawOriginal('seat_type');
                $price = $pricesBySeatType[$seatType] ?? ((int) $seat->getAttribute('price_minor_units') ?: $basePriceMinorUnits);
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
