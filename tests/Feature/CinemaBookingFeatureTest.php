<?php

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\ExpireBooking;
use App\Actions\Booking\HoldSeats;
use App\Actions\Booking\PayBooking;
use App\Actions\Cinema\AddConcessions;
use App\Actions\Cinema\CheckInTicket;
use App\Actions\Cinema\CreateScreening;
use App\Enums\Booking\BookingStatus;
use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Cinema\TicketStatus;
use App\Models\Cinema\Concession;
use App\Models\Cinema\Movie;
use App\Models\Cinema\ScreeningRoom;
use App\Models\Cinema\Seat;
use App\Models\User;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use App\Support\Booking\SeatHoldConflict;
use App\Support\Cinema\TicketQrCode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets guests browse movies and seats before requiring authentication to hold them', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);

    $this->get(route('cinema.movies.index'))->assertOk()->assertSee($screening->movie->title);
    $this->get(route('cinema.movies.show', $screening->movie))->assertOk()->assertSee($room->name);
    $this->get(route('cinema.screenings.show', $screening))->assertOk()->assertSee((string) $seat->seat_number);
    $this->post(route('user.screenings.hold', $screening), ['seat_ids' => [$seat->id], 'idempotency_key' => 'guest-hold'])->assertRedirect(route('login'));
});

it('holds a concrete seat and prevents a second user from taking it', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create(['row_label' => 'A', 'seat_number' => 1, 'price_minor_units' => 120000]);
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $first = User::factory()->create();
    $second = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($first, $screening, [$seat->id], 'cinema-1');
    expect($booking->items)->toHaveCount(1)->and($booking->status)->toBe(BookingStatus::Held)->and($booking->items->first()->screeningSeat->status)->toBe(ScreeningSeatStatus::Held);
    expect(fn () => app(HoldSeats::class)->execute($second, $screening, [$seat->id], 'cinema-2'))->toThrow(SeatHoldConflict::class);
});

it('preserves a guest seat selection through login before creating the hold', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();

    $this->post(route('cinema.screenings.hold', $screening), ['seat_ids' => [$seat->id], 'idempotency_key' => 'resume-hold'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('cinema.pending_hold.seat_ids', [$seat->id]);

    $response = $this->actingAs($user)->get(route('user.cinema.hold.resume'));
    $booking = $user->bookings()->where('screening_id', $screening->id)->firstOrFail();

    $response->assertRedirect(route('user.bookings.show', $booking));
});

it('converts held seats into sold tickets after payment', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create(['row_label' => 'B', 'seat_number' => 2]);
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'cinema-pay-1');
    $payment = app(PayBooking::class)->execute($booking);
    $item = $booking->items()->with('screeningSeat')->firstOrFail();
    expect($payment->getAttribute('status')->value)->toBe('succeeded')->and($booking->refresh()->status)->toBe(BookingStatus::Confirmed)->and($item->screeningSeat->status)->toBe(ScreeningSeatStatus::Sold)->and($item->refresh()->status)->toBe(TicketStatus::Issued)->and($item->ticket_code)->toStartWith('TKT-');
});

it('does not allow cancelling a paid booking without going through refund', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'cancel-paid-1');
    app(PayBooking::class)->execute($booking);

    expect(fn () => app(CancelBooking::class)->execute($booking))->toThrow(InvalidBookingTransition::class);
});

it('does not allow a user to cancel another users booking', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($owner, $screening, [$seat->id], 'cancel-owner-1');

    $this->actingAs($otherUser)
        ->patch(route('user.bookings.cancel', $booking), ['reason' => 'Not my booking'])
        ->assertForbidden();

    expect($booking->refresh()->status)->toBe(BookingStatus::Held);
});

it('releases expired cinema holds and snapshots combo pricing', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'cinema-expire-1');
    $concession = Concession::query()->create(['name' => 'Combo', 'sku' => 'COMBO-1', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);
    app(AddConcessions::class)->execute($booking, [$concession->id => 2]);
    expect($booking->refresh()->total_minor_units)->toBe(200000)->and($concession->refresh()->stock)->toBe(1);
    $booking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();
    $screeningSeat = $screening->screeningSeats()->firstOrFail();
    $screeningSeat->forceFill(['held_until' => now()->subMinute()])->saveQuietly();
    expect($screeningSeat->refresh()->isAvailableForSelection())->toBeTrue();
    app(ExpireBooking::class)->execute($booking);
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)->and($screeningSeat->refresh()->status)->toBe(ScreeningSeatStatus::Available);
});

it('checks in a paid ticket once inside the configured screening window', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $start = now()->addDay()->startOfHour();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, $start->toDateTimeString(), $start->copy()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'cinema-checkin-1');
    app(PayBooking::class)->execute($booking);
    $item = $booking->items()->firstOrFail();
    CarbonImmutable::setTestNow(CarbonImmutable::parse((string) $screening->getRawOriginal('starts_at'), 'UTC')->subMinutes(30));
    app(CheckInTicket::class)->execute($item->ticket_code, User::factory()->create(['is_admin' => true])->id);
    expect($item->refresh()->status)->toBe(TicketStatus::CheckedIn);
    CarbonImmutable::setTestNow();
});

it('renders a signed ticket payload as an SVG QR code', function (): void {
    $svg = app(TicketQrCode::class)->render('https://example.test/ticket/TKT-123');
    expect($svg)->toContain('<svg')->and($svg)->toContain('viewBox');
});
