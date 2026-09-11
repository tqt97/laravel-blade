<?php

use App\Actions\Movie\Catalog\CreateScreening;
use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Models\Movie\Movie;
use App\Models\Movie\ScreeningRoom;
use App\Models\Movie\Seat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (getenv('BOOKING_MYSQL_CONCURRENCY') !== '1'
        || ! extension_loaded('pdo_mysql')
        || ! function_exists('pcntl_fork')
        || config('database.default') !== 'mysql') {
        $this->markTestSkipped('Enable BOOKING_MYSQL_CONCURRENCY=1 with pdo_mysql, pcntl and a MySQL default connection.');
    }
});

it('allows only one process to reserve the last combo stock', function (): void {
    $concession = Concession::query()->create([
        'name' => 'Concurrent Combo',
        'sku' => 'MYSQL-CONCURRENT-COMBO',
        'price_minor_units' => 50000,
        'currency' => 'VND',
        'stock' => 1,
        'is_active' => true,
    ]);

    $results = runConcurrentStockReservations($concession->getKey());

    expect($results)->toHaveCount(2)
        ->and(collect($results)->filter()->count())->toBe(1)
        ->and($concession->refresh()->stock)->toBe(0);
});

it('allows only one process to own a screening seat', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $screeningSeat = $screening->screeningSeats()->firstOrFail();
    $bookings = collect([1, 2])->map(fn (): Booking => Booking::createHeld([
        'user_id' => null,
        'screening_id' => $screening->getKey(),
        'expires_at' => now()->addMinutes(10),
        'amount_minor_units' => 100000,
        'currency' => 'VND',
        'subtotal_minor_units' => 100000,
        'discount_minor_units' => 0,
        'total_minor_units' => 100000,
        'pricing_currency' => 'VND',
    ]));

    $results = runConcurrentSeatReservations($screeningSeat->getKey(), $bookings->pluck('id')->all());

    expect($results)->toHaveCount(2)
        ->and(collect($results)->filter()->count())->toBe(1)
        ->and($screeningSeat->refresh()->status)->toBe(ScreeningSeatStatus::Held);
});

/** @return list<bool> */
function runConcurrentStockReservations(int $concessionId): array
{
    return runConcurrentWorkers(function () use ($concessionId): bool {
        $connection = DB::connection('mysql');
        $connection->beginTransaction();
        try {
            $concession = $connection->table('concessions')->where('id', $concessionId)->lockForUpdate()->first();
            $won = $concession !== null && $concession->stock > 0;
            if ($won) {
                $connection->table('concessions')->where('id', $concessionId)->decrement('stock');
            }
            $connection->commit();

            return $won;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    });
}

/** @param list<int> $bookingIds */
/** @return list<bool> */
function runConcurrentSeatReservations(int $screeningSeatId, array $bookingIds): array
{
    return runConcurrentWorkers(function (int $worker) use ($screeningSeatId, $bookingIds): bool {
        $connection = DB::connection('mysql');
        $connection->beginTransaction();
        try {
            $screeningSeat = $connection->table('screening_seats')->where('id', $screeningSeatId)->lockForUpdate()->first();
            $won = $screeningSeat !== null && $screeningSeat->status === ScreeningSeatStatus::Available->value;
            if ($won) {
                $connection->table('screening_seats')->where('id', $screeningSeatId)->update([
                    'status' => ScreeningSeatStatus::Held->value,
                    'held_by_booking_id' => $bookingIds[$worker],
                ]);
            }
            $connection->commit();

            return $won;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    });
}

/** @param Closure(int): bool $worker */
/** @return list<bool> */
function runConcurrentWorkers(Closure $worker): array
{
    $directory = sys_get_temp_dir().'/movie-booking-concurrency-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700, true);
    $startFile = $directory.'/start';
    $children = [];

    try {
        foreach ([0, 1] as $index) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::purge('mysql');
                while (! is_file($startFile)) {
                    usleep(1000);
                }
                file_put_contents($directory.'/result-'.$index, $worker($index) ? '1' : '0');
                exit(0);
            }
            $children[] = $pid;
        }

        touch($startFile);
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            expect(pcntl_wifexited($status))->toBeTrue();
        }

        return array_map(
            static fn (int $index): bool => file_get_contents($directory.'/result-'.$index) === '1',
            [0, 1],
        );
    } finally {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
