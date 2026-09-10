<?php

namespace App\Actions\Movie\Catalog;

use App\Enums\Movie\Seating\SeatType;
use App\Models\Movie\ScreeningRoom;
use Illuminate\Support\Facades\DB;

final class CreateScreeningRoom
{
    /** @param array{name: string, code: string, timezone: string, rows: string, seats_per_row: int} $attributes */
    public function execute(array $attributes): ScreeningRoom
    {
        return DB::transaction(function () use ($attributes): ScreeningRoom {
            $room = ScreeningRoom::query()->create([
                'name' => $attributes['name'],
                'code' => $attributes['code'],
                'timezone' => $attributes['timezone'],
                'is_active' => true,
            ]);

            foreach (array_filter(array_map('trim', explode(',', $attributes['rows']))) as $row) {
                for ($number = 1; $number <= $attributes['seats_per_row']; $number++) {
                    $room->seats()->create([
                        'row_label' => $row,
                        'seat_number' => $number,
                        'seat_type' => SeatType::Regular,
                        'price_minor_units' => 0,
                        'is_active' => true,
                    ]);
                }
            }

            return $room->refresh();
        }, 3);
    }
}
