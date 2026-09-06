<?php

namespace App\Actions\Cinema;

use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Cinema\ScreeningStatus;
use App\Models\Cinema\Movie;
use App\Models\Cinema\Screening;
use App\Models\Cinema\ScreeningRoom;
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
            if (Screening::query()->where('screening_room_id', $room->getKey())->where('status', ScreeningStatus::Scheduled)->where('starts_at', '<', $ends)->where('ends_at', '>', $starts)->exists()) {
                throw new ScreeningConflict('The screening overlaps another screening in this room.');
            }
            $screening = Screening::query()->create([
                'movie_id' => $movie->getKey(), 'screening_room_id' => $room->getKey(),
                'starts_at' => $starts, 'ends_at' => $ends, 'status' => ScreeningStatus::Scheduled,
                'base_price_minor_units' => $basePriceMinorUnits, 'currency' => strtoupper($currency),
            ]);
            $seats = $room->seats()->where('is_active', true)->get();
            foreach ($pricesBySeatType as $seatType => $price) {
                $screening->prices()->create(['seat_type' => $seatType, 'price_minor_units' => $price, 'currency' => strtoupper($currency)]);
            }
            foreach ($seats as $seat) {
                $seatType = (string) $seat->getRawOriginal('seat_type');
                $price = $pricesBySeatType[$seatType] ?? ((int) $seat->getAttribute('price_minor_units') ?: $basePriceMinorUnits);
                $screening->screeningSeats()->create([
                    'seat_id' => $seat->getKey(), 'status' => ScreeningSeatStatus::Available,
                    'price_minor_units' => $price, 'currency' => strtoupper($currency),
                ]);
            }

            return $screening->load('screeningSeats');
        }, 3);
    }
}
