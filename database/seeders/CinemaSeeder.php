<?php

namespace Database\Seeders;

use App\Actions\Booking\HoldSeats;
use App\Actions\Booking\PayBooking;
use App\Actions\Cinema\AddConcessions;
use App\Actions\Cinema\CreateScreening;
use App\Enums\Cinema\SeatType;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Concession;
use App\Models\Cinema\Movie;
use App\Models\Cinema\Screening;
use App\Models\Cinema\ScreeningRoom;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CinemaSeeder extends Seeder
{
    public function run(): void
    {
        $movies = collect([
            ['title' => 'The Last Horizon', 'synopsis' => 'A pilot crosses an unknown frontier to bring a lost crew home.', 'duration_minutes' => 128, 'rating' => 'PG-13', 'release_date' => now()->subDays(5)->toDateString()],
            ['title' => 'Midnight in Saigon', 'synopsis' => 'One night, two strangers and a city full of unfinished stories.', 'duration_minutes' => 114, 'rating' => 'PG', 'release_date' => now()->subDays(12)->toDateString()],
            ['title' => 'Ocean of Stars', 'synopsis' => 'A family discovers that the sea keeps memories of the people we love.', 'duration_minutes' => 102, 'rating' => 'G', 'release_date' => now()->subDays(20)->toDateString()],
            ['title' => 'The Clockmaker', 'synopsis' => 'A quiet craftsman gets one chance to repair a day that went wrong.', 'duration_minutes' => 121, 'rating' => 'PG-13', 'release_date' => now()->subDays(30)->toDateString()],
            ['title' => 'Neon District', 'synopsis' => 'A detective follows a signal through the city after the lights go out.', 'duration_minutes' => 109, 'rating' => 'R', 'release_date' => now()->subDays(8)->toDateString()],
            ['title' => 'Little Comets', 'synopsis' => 'An animated adventure about friendship, courage and finding your way home.', 'duration_minutes' => 96, 'rating' => 'G', 'release_date' => now()->subDays(15)->toDateString()],
        ])->map(function (array $attributes): Movie {
            $attributes['slug'] = Str::slug($attributes['title']);

            return Movie::query()->updateOrCreate(['title' => $attributes['title']], $attributes);
        });

        $rooms = collect([
            ['name' => 'Aurora Hall', 'code' => 'AURORA', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Starlight Hall', 'code' => 'STARLIGHT', 'timezone' => 'Asia/Ho_Chi_Minh'],
        ])->map(function (array $attributes): ScreeningRoom {
            $room = ScreeningRoom::query()->updateOrCreate(['code' => $attributes['code']], $attributes + ['is_active' => true]);
            for ($row = 0; $row < 6; $row++) {
                for ($number = 1; $number <= 12; $number++) {
                    $room->seats()->updateOrCreate(['row_label' => chr(65 + $row), 'seat_number' => $number], ['seat_type' => $row < 2 ? SeatType::Vip : SeatType::Regular, 'price_minor_units' => $row < 2 ? 150000 : 100000, 'is_active' => true]);
                }
            }

            return $room;
        });

        $screenings = collect();
        foreach ($movies as $movieIndex => $movie) {
            $room = $rooms[$movieIndex % $rooms->count()];
            foreach ([11, 15, 19] as $hour) {
                $startsAt = CarbonImmutable::tomorrow($room->timezone)->addDays($movieIndex)->setTime($hour, 0);
                $screening = Screening::query()->where('movie_id', $movie->id)->where('screening_room_id', $room->id)->where('starts_at', $startsAt->utc())->first();
                if ($screening === null) {
                    $screening = app(CreateScreening::class)->execute($movie, $room, $startsAt->toDateTimeString(), $startsAt->addMinutes($movie->duration_minutes + 20)->toDateTimeString(), 100000, 'VND', ['vip' => 150000]);
                }
                $screenings->push($screening);
            }
        }

        $concessions = collect([
            ['name' => 'Classic Popcorn', 'sku' => 'POP-CLASSIC', 'price_minor_units' => 55000, 'stock' => 100],
            ['name' => 'Caramel Popcorn', 'sku' => 'POP-CARAMEL', 'price_minor_units' => 65000, 'stock' => 100],
            ['name' => 'Large Combo', 'sku' => 'COMBO-LARGE', 'price_minor_units' => 120000, 'stock' => 50],
            ['name' => 'Mineral Water', 'sku' => 'WATER-500', 'price_minor_units' => 25000, 'stock' => 200],
        ])->map(function (array $attributes): Concession {
            return Concession::query()->updateOrCreate(
                ['sku' => $attributes['sku']],
                $attributes + ['currency' => 'VND', 'is_active' => true],
            );
        });

        $this->seedDemoOrders($screenings->first(), $concessions);
    }

    /** @param Collection<int, Concession> $concessions */
    private function seedDemoOrders(?Screening $screening, Collection $concessions): void
    {
        $user = User::query()->where('email', 'user@gmail.com')->first();
        if ($user === null || $screening === null) {
            return;
        }
        $screening->load('screeningSeats');
        $seats = $screening->screeningSeats->take(3);
        if ($seats->count() < 3 || Booking::query()->where('user_id', $user->id)->where('screening_id', $screening->id)->exists()) {
            return;
        }
        $paid = app(HoldSeats::class)->execute($user, $screening, [(int) $seats[0]->getAttribute('seat_id'), (int) $seats[1]->getAttribute('seat_id')], 'seed-paid-'.$screening->id);
        app(AddConcessions::class)->execute($paid, [$concessions->first()->id => 2, $concessions->get(2)->id => 1]);
        app(PayBooking::class)->execute($paid);
        app(HoldSeats::class)->execute($user, $screening, [(int) $seats[2]->getAttribute('seat_id')], 'seed-held-'.$screening->id);
    }
}
