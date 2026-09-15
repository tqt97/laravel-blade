<?php

use App\Actions\Booking\Checkout\HoldSeats;
use App\Actions\Catalog\CreateScreening;
use App\Actions\Commerce\Coupons\ApplyCoupon;
use App\Models\Booking\BookingItem;
use App\Models\Catalog\Movie;
use App\Models\Catalog\ScreeningRoom;
use App\Models\Catalog\Seat;
use App\Models\Commerce\Coupon;
use App\Models\Commerce\CouponReservation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows only one reserved coupon per booking while retaining released history', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'phase-zero-coupon');
    $first = Coupon::factory()->create(['code' => 'PHASE-FIRST']);
    $second = Coupon::factory()->create(['code' => 'PHASE-SECOND']);

    app(ApplyCoupon::class)->execute($booking, $first->code);
    app(ApplyCoupon::class)->execute($booking->refresh(), $second->code);

    expect($booking->refresh()->coupon_id)->toBe($second->id)
        ->and($booking->couponReservations()->where('status', 'reserved')->count())->toBe(1)
        ->and($booking->couponReservations()->where('status', 'released')->count())->toBe(1);

    expect(fn () => CouponReservation::query()->create([
        'coupon_id' => $first->id,
        'booking_id' => $booking->id,
        'status' => 'reserved',
    ]))->toThrow(QueryException::class);
});

it('rejects a booking item whose seat belongs to another screening', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $movie = Movie::factory()->create();
    $screeningOne = app(CreateScreening::class)->execute($movie, $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $screeningTwo = app(CreateScreening::class)->execute($movie, $room, now()->addDays(2)->toDateTimeString(), now()->addDays(2)->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screeningOne, [$seat->id], 'phase-zero-item');
    $otherSeat = $screeningTwo->screeningSeats()->firstOrFail();

    expect(fn () => BookingItem::query()->create([
        'booking_id' => $booking->id,
        'screening_seat_id' => $otherSeat->id,
        'ticket_code' => 'PHASE-MISMATCH',
        'price_minor_units' => 100000,
        'currency' => 'VND',
        'status' => 'reserved',
    ]))->toThrow(QueryException::class);
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
