<?php

namespace Database\Seeders;

use App\Actions\Movie\Booking\HoldSeats;
use App\Actions\Movie\Booking\PayBooking;
use App\Actions\Movie\Catalog\CreateScreening;
use App\Actions\Movie\Concessions\AddConcessions;
use App\Enums\Movie\Booking\CouponType;
use App\Enums\Movie\Seating\SeatType;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Models\Movie\Coupon;
use App\Models\Movie\Movie;
use App\Models\Movie\Screening;
use App\Models\Movie\ScreeningRoom;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $movies = collect([
            ['title' => 'The Last Horizon', 'synopsis' => 'A pilot crosses an unknown frontier to bring a lost crew home.', 'duration_minutes' => 128, 'rating' => 'PG-13', 'poster_path' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=900&q=85', 'release_date' => now()->subDays(5)->toDateString()],
            ['title' => 'Midnight in Saigon', 'synopsis' => 'One night, two strangers and a city full of unfinished stories.', 'duration_minutes' => 114, 'rating' => 'PG', 'poster_path' => 'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?auto=format&fit=crop&w=900&q=85', 'release_date' => now()->subDays(12)->toDateString()],
            ['title' => 'Ocean of Stars', 'synopsis' => 'A family discovers that the sea keeps memories of the people we love.', 'duration_minutes' => 102, 'rating' => 'G', 'poster_path' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=85', 'release_date' => now()->subDays(20)->toDateString()],
            ['title' => 'The Clockmaker', 'synopsis' => 'A quiet craftsman gets one chance to repair a day that went wrong.', 'duration_minutes' => 121, 'rating' => 'PG-13', 'poster_path' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?auto=format&fit=crop&w=900&q=85', 'release_date' => now()->subDays(30)->toDateString()],
            ['title' => 'Neon District', 'synopsis' => 'A detective follows a signal through the city after the lights go out.', 'duration_minutes' => 109, 'rating' => 'R', 'poster_path' => 'https://images.unsplash.com/photo-1519608487953-e999c86e7455?auto=format&fit=crop&w=900&q=85', 'release_date' => now()->subDays(8)->toDateString()],
            ['title' => 'Little Comets', 'synopsis' => 'An animated adventure about friendship, courage and finding your way home.', 'duration_minutes' => 96, 'rating' => 'G', 'poster_path' => 'https://images.unsplash.com/photo-1535016120720-40c646be5580?auto=format&fit=crop&w=900&q=85', 'release_date' => now()->subDays(15)->toDateString()],
            ['title' => 'Paper Moons', 'synopsis' => 'A young photographer follows a trail of mysterious postcards across the city.', 'duration_minutes' => 111, 'rating' => 'PG', 'poster_path' => 'https://images.unsplash.com/photo-1485846234645-a62644f84728?auto=format&fit=crop&w=900&q=85', 'release_date' => now()->subDays(3)->toDateString()],
            ['title' => 'After the Rain', 'synopsis' => 'When the storm clears, an old theater reveals one final unfinished performance.', 'duration_minutes' => 118, 'rating' => 'PG-13', 'poster_path' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=900&q=85&sat=-35', 'release_date' => now()->subDays(1)->toDateString()],
            ['title' => 'Echoes of Tomorrow', 'synopsis' => 'A sound designer uncovers a message from the future hidden inside an old recording.', 'duration_minutes' => 105, 'rating' => 'PG-13', 'poster_path' => 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?auto=format&fit=crop&w=900&q=85', 'release_date' => now()->subDays(6)->toDateString()],
        ])->merge(collect([
            'The Glass Garden', 'Northbound', 'Velvet Skies', 'The Silent Code', 'Wildflower Season',
            'Gravity of Us', 'The Art of Leaving', 'Blue Hour', 'Signal Fires', 'The Cartographer',
            'Sunset Archive', 'A Thousand Mornings', 'The Hidden Room', 'Parallel Lines', 'Golden Hour',
            'River of Light', 'The Long Way Home', 'Small Wonders', 'Chasing Monsoon', 'The Final Reel',
            'Constellation Road',
        ])->values()->map(function (string $title, int $index): array {
            return [
                'title' => $title,
                'synopsis' => 'A new story unfolds when an ordinary life takes an unexpected turn.',
                'duration_minutes' => 98 + ($index % 7) * 5,
                'rating' => ['G', 'PG', 'PG-13'][$index % 3],
                'poster_path' => 'https://picsum.photos/seed/cinepass-movie-'.($index + 10).'/900/1350',
                'release_date' => now()->subDays(7 + $index)->toDateString(),
            ];
        }))->map(function (array $attributes): Movie {
            $attributes['slug'] = Str::slug($attributes['title']);
            $attributes['genre'] = match ($attributes['title']) {
                'Little Comets' => 'Animation · Family',
                'Midnight in Saigon', 'After the Rain' => 'Drama · Romance',
                'Neon District' => 'Crime · Thriller',
                'Ocean of Stars' => 'Adventure · Family',
                'The Clockmaker', 'Paper Moons' => 'Fantasy · Drama',
                default => 'Adventure · Sci-Fi',
            };
            $attributes['director'] = 'Aurora Pictures';
            $attributes['cast'] = ['Mia Nguyen', 'Daniel Park', 'Linh Tran'];
            $attributes['language'] = 'Vietnamese';
            $attributes['format'] = '2D';
            $attributes['backdrop_path'] = str_replace('w=900', 'w=1600', $attributes['poster_path']);

            return Movie::query()->updateOrCreate(['title' => $attributes['title']], $attributes);
        });

        $rooms = collect([
            ['name' => 'Aurora Hall', 'code' => 'AURORA', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Starlight Hall', 'code' => 'STARLIGHT', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Moonlight Hall', 'code' => 'MOONLIGHT', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Nova Hall', 'code' => 'NOVA', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Orbit Hall', 'code' => 'ORBIT', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Stellar Hall', 'code' => 'STELLAR', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Cosmos Hall', 'code' => 'COSMOS', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Galaxy Hall', 'code' => 'GALAXY', 'timezone' => 'Asia/Ho_Chi_Minh'],
            ['name' => 'Eclipse Hall', 'code' => 'ECLIPSE', 'timezone' => 'Asia/Ho_Chi_Minh'],
        ])->merge(collect(range(1, 21))->map(function (int $number): array {
            return ['name' => 'Studio '.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'code' => 'STUDIO-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'timezone' => 'Asia/Ho_Chi_Minh'];
        }))->map(function (array $attributes): ScreeningRoom {
            $room = ScreeningRoom::query()->updateOrCreate(['code' => $attributes['code']], $attributes + ['is_active' => true]);
            for ($row = 0; $row < 6; $row++) {
                for ($number = 1; $number <= 12; $number++) {
                    $room->seats()->updateOrCreate(['row_label' => chr(65 + $row), 'seat_number' => $number], ['seat_type' => $row < 2 ? SeatType::Vip : SeatType::Regular, 'price_minor_units' => $row < 2 ? 150000 : 100000, 'is_active' => true]);
                }
            }

            return $room;
        });

        $screenings = collect();
        $dailyShowtimes = [[9, 14, 19], [10, 15, 20], [11, 16, 21]];
        foreach ($movies as $movieIndex => $movie) {
            $room = $rooms[$movieIndex];
            foreach ([0, 1, 2] as $dayOffset) {
                $hours = $dailyShowtimes[($movieIndex + $dayOffset) % count($dailyShowtimes)];
                foreach ($hours as $hour) {
                    $startsAt = CarbonImmutable::tomorrow($room->timezone)->addDays($dayOffset)->setTime($hour, 0);
                    $screening = Screening::query()
                        ->where('movie_id', $movie->id)
                        ->where('screening_room_id', $room->id)
                        ->where('starts_at', $startsAt)
                        ->first();
                    if ($screening === null) {
                        $screening = app(CreateScreening::class)->execute(
                            $movie,
                            $room,
                            $startsAt->toDateTimeString(),
                            $startsAt->addMinutes($movie->duration_minutes + 20)->toDateTimeString(),
                            100000,
                            'VND',
                            ['vip' => 150000],
                        );
                    }
                    $screenings->push($screening);
                }
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

        /**
         * Keep deterministic coupon fixtures available for local development and UI review.
         *
         * Do not include used_count in the update attributes: re-running the seeder must
         * update the campaign configuration without erasing real usage from an existing DB.
         */
        $couponDefinitions = [
            [
                'code' => 'MOVIE10',
                'type' => CouponType::Percentage,
                'value' => 10,
                'maximum_discount_minor_units' => 50000,
                'currency' => 'VND',
                'usage_limit' => 1000,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'WELCOME50K',
                'type' => CouponType::Fixed,
                'value' => 50000,
                'maximum_discount_minor_units' => null,
                'currency' => 'VND',
                'usage_limit' => 100,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonth(),
                'is_active' => true,
            ],
            [
                'code' => 'VIP15',
                'type' => CouponType::Percentage,
                'value' => 15,
                'maximum_discount_minor_units' => 100000,
                'currency' => 'VND',
                'usage_limit' => 250,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(2),
                'is_active' => true,
            ],
            [
                'code' => 'EARLYBIRD20',
                'type' => CouponType::Percentage,
                'value' => 20,
                'maximum_discount_minor_units' => 75000,
                'currency' => 'VND',
                'usage_limit' => 500,
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addMonths(2),
                'is_active' => true,
            ],
            [
                'code' => 'EXPIRED5',
                'type' => CouponType::Percentage,
                'value' => 5,
                'maximum_discount_minor_units' => 25000,
                'currency' => 'VND',
                'usage_limit' => 50,
                'starts_at' => now()->subMonths(2),
                'ends_at' => now()->subDay(),
                'is_active' => true,
            ],
            [
                'code' => 'PAUSED10',
                'type' => CouponType::Percentage,
                'value' => 10,
                'maximum_discount_minor_units' => 50000,
                'currency' => 'VND',
                'usage_limit' => 100,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonth(),
                'is_active' => false,
            ],
        ];

        foreach ($couponDefinitions as $couponDefinition) {
            Coupon::query()->updateOrCreate(
                ['code' => $couponDefinition['code']],
                $couponDefinition,
            );
        }

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
        $seats = $screening->screeningSeats->take(2);
        $heldScreening = Screening::query()
            ->where('id', '!=', $screening->getKey())
            ->where('starts_at', '>', now())
            ->orderBy('id')
            ->first();

        if ($seats->count() < 2 || $heldScreening === null || Booking::query()->where('user_id', $user->id)->where('screening_id', $screening->id)->exists()) {
            return;
        }
        $paid = app(HoldSeats::class)->execute($user, $screening, [(int) $seats[0]->getAttribute('seat_id'), (int) $seats[1]->getAttribute('seat_id')], 'seed-paid-'.$screening->id);
        app(AddConcessions::class)->execute($paid, [$concessions->first()->id => 2, $concessions->get(2)->id => 1]);
        app(PayBooking::class)->execute($paid);
        $heldScreening->load('screeningSeats');
        $heldSeat = $heldScreening->screeningSeats->first();
        if ($heldSeat !== null && ! Booking::query()->where('user_id', $user->id)->where('screening_id', $heldScreening->id)->exists()) {
            app(HoldSeats::class)->execute($user, $heldScreening, [(int) $heldSeat->getAttribute('seat_id')], 'seed-held-'.$heldScreening->id);
        }
    }
}
