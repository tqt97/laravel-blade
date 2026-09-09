<?php

use App\Models\Movie\Concession;
use App\Models\Movie\Coupon;
use App\Models\Movie\Movie;
use App\Models\Movie\Screening;
use App\Models\Movie\ScreeningRoom;
use App\Models\Movie\ScreeningSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets administrators create movies rooms and screenings with materialized seats', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.cinema.movies.store'), ['title' => 'Cinema Test', 'duration_minutes' => 120])->assertRedirect();
    $this->actingAs($admin)->post(route('admin.cinema.rooms.store'), ['name' => 'Room 1', 'code' => 'R1', 'timezone' => 'Asia/Ho_Chi_Minh', 'rows' => 'A,B', 'seats_per_row' => 3])->assertRedirect();
    $movie = Movie::query()->firstOrFail();
    $room = ScreeningRoom::query()->firstOrFail();
    $this->actingAs($admin)->post(route('admin.cinema.screenings.store'), ['movie_id' => $movie->id, 'screening_room_id' => $room->id, 'starts_at' => now()->addDay()->format('Y-m-d H:i:s'), 'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'), 'base_price_minor_units' => 100000, 'currency' => 'VND'])->assertRedirect();
    expect(Screening::query()->count())->toBe(1)->and(ScreeningSeat::query()->count())->toBe(6);
});

it('lets administrators manage concessions used during checkout', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.cinema.concessions.index'))->assertOk();
    $this->actingAs($admin)->post(route('admin.cinema.concessions.store'), [
        'name' => 'Large Popcorn',
        'sku' => 'POP-LARGE',
        'price_minor_units' => 75000,
        'currency' => 'vnd',
        'stock' => 20,
        'is_active' => 1,
    ])->assertRedirect();

    $concession = Concession::query()->where('sku', 'POP-LARGE')->firstOrFail();
    expect($concession->currency)->toBe('VND')->and($concession->stock)->toBe(20);

    $this->actingAs($admin)->patch(route('admin.cinema.concessions.update', $concession), [
        'name' => 'Large Popcorn Updated',
        'sku' => 'POP-LARGE',
        'price_minor_units' => 80000,
        'currency' => 'VND',
        'stock' => 15,
        'stock_reason' => 'Cycle count correction',
        'is_active' => 1,
    ])->assertRedirect();

    expect($concession->refresh()->name)->toBe('Large Popcorn Updated')->and($concession->price_minor_units)->toBe(80000)->and($concession->stock)->toBe(15);
});

it('lets administrators create coupons for movie checkout', function (): void {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.cinema.coupons.store'), [
        'code' => 'movie20',
        'type' => 'percentage',
        'value' => 20,
        'maximum_discount_minor_units' => 50000,
        'currency' => 'vnd',
        'usage_limit' => 25,
        'is_active' => 1,
    ])->assertRedirect();

    expect(Coupon::query()->where('code', 'MOVIE20')->firstOrFail()->value)->toBe(20);
});
