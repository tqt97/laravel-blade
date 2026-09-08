<?php

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\ExpireBooking;
use App\Actions\Booking\FinalizeSuccessfulPayment;
use App\Actions\Booking\HoldSeats;
use App\Actions\Booking\PayBooking;
use App\Actions\Cinema\AddConcessions;
use App\Actions\Cinema\CheckInTicket;
use App\Actions\Cinema\CreateScreening;
use App\Enums\Booking\BookingStatus;
use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Cinema\TicketStatus;
use App\Enums\Payment\PaymentStatus;
use App\Jobs\PublishOutboxMessage;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Concession;
use App\Models\Cinema\Movie;
use App\Models\Cinema\ScreeningRoom;
use App\Models\Cinema\Seat;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\User;
use App\Queries\Cinema\BookingReport;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use App\Support\Booking\SeatHoldConflict;
use App\Support\Cinema\TicketQrCode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

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

it('returns the same booking for a repeated idempotent hold request', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();

    $first = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'same-request');
    $second = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'same-request');

    expect($second->is($first))->toBeTrue()->and(Booking::query()->where('user_id', $user->id)->count())->toBe(1);
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

    $response->assertRedirect(route('user.bookings.checkout', $booking));
});

it('sends a held booking through checkout and then to the ticket success page after payment', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();

    $holdResponse = $this->actingAs($user)->post(route('user.screenings.hold', $screening), [
        'seat_ids' => [$seat->id],
        'idempotency_key' => 'checkout-flow',
    ]);
    $booking = $user->bookings()->latest('id')->firstOrFail();

    $holdResponse->assertRedirect(route('user.bookings.checkout', $booking));
    $this->actingAs($user)->get(route('user.screenings.show', $screening))
        ->assertOk()
        ->assertSee('You are holding 1 seat(s) for this screening.');
    $this->actingAs($user)->get(route('user.bookings.checkout', $booking))->assertOk();
    Concession::query()->create(['name' => 'Large Popcorn', 'sku' => 'FLOW-POPCORN', 'price_minor_units' => 75000, 'currency' => 'VND', 'stock' => 10, 'is_active' => true]);
    $this->actingAs($user)->get(route('user.bookings.combos', $booking))->assertOk()->assertSee('Large Popcorn');

    $paymentResponse = $this->actingAs($user)->post(route('user.bookings.pay', $booking));

    $paymentResponse->assertRedirect(route('user.bookings.success', $booking));
    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
    $this->actingAs($user)->get(route('user.bookings.success', $booking))->assertOk()->assertSee('TKT-');
});

it('converts held seats into sold tickets after payment', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create(['row_label' => 'B', 'seat_number' => 2]);
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'cinema-pay-1');
    $payment = app(PayBooking::class)->execute($booking);
    $item = $booking->items()->with('screeningSeat')->firstOrFail();
    expect($payment->getAttribute('status')->value)->toBe('succeeded')->and($booking->refresh()->status)->toBe(BookingStatus::Confirmed)->and($item->screeningSeat->status)->toBe(ScreeningSeatStatus::Sold)->and($item->refresh()->status)->toBe(TicketStatus::Issued)->and($item->ticket_code)->toStartWith('TKT-');
    $verifyUrl = URL::temporarySignedRoute('user.tickets.verify', now()->addHour(), ['ticket' => $item->ticket_code]);
    $this->get($verifyUrl)->assertOk()->assertSee($screening->movie->title)->assertSee('Issued');
});

it('finalizes successful Stripe webhooks idempotently', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'stripe-webhook-1');
    $payment = $booking->payment()->create(['provider' => 'stripe', 'provider_payment_id' => 'pi_webhook_1', 'status' => PaymentStatus::Pending, 'amount_minor_units' => $booking->amount_minor_units, 'currency' => 'VND']);
    $payload = ['id' => 'evt_webhook_1', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => $payment->provider_payment_id]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $server = ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'];
    $this->call('POST', route('webhooks.stripe'), [], [], [], $server, $body)->assertNoContent();
    $this->call('POST', route('webhooks.stripe'), [], [], [], $server, $body)->assertNoContent();

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->items()->firstOrFail()->refresh()->status)->toBe(TicketStatus::Issued)
        ->and($booking->items()->firstOrFail()->ticket_code)->toStartWith('TKT-');
    expect(OutboxMessage::query()->where('event_type', 'booking.payment_succeeded')->count())->toBe(1);
});

it('requires a refund instead of issuing a ticket when payment succeeds after expiry', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'payment-expired-race-1');
    $booking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();
    $booking->items()->firstOrFail()->screeningSeat()->update(['held_until' => now()->subMinute()]);
    $payment = $booking->payment()->create(['provider' => 'fake', 'provider_payment_id' => 'fake_expired_1', 'status' => PaymentStatus::Succeeded, 'amount_minor_units' => $booking->amount_minor_units, 'currency' => 'VND']);

    app(FinalizeSuccessfulPayment::class)->execute($payment);

    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::RequiresRefund)
        ->and($booking->items()->firstOrFail()->refresh()->status)->toBe(TicketStatus::Cancelled)
        ->and($booking->items()->firstOrFail()->screeningSeat()->firstOrFail()->status)->toBe(ScreeningSeatStatus::Available);
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
    $user = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'cinema-expire-1');
    $concession = Concession::query()->create(['name' => 'Combo', 'sku' => 'COMBO-1', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);
    app(AddConcessions::class)->execute($booking, [$concession->id => 2]);
    expect($booking->refresh()->total_minor_units)->toBe(200000)->and($concession->refresh()->stock)->toBe(1);
    $booking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();
    $screeningSeat = $screening->screeningSeats()->firstOrFail();
    $screeningSeat->forceFill(['held_until' => now()->subMinute()])->saveQuietly();
    expect($screeningSeat->refresh()->isAvailableForSelection())->toBeTrue();
    app(ExpireBooking::class)->execute($booking);
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)->and($screeningSeat->refresh()->status)->toBe(ScreeningSeatStatus::Available)->and($concession->refresh()->stock)->toBe(3);
    $this->actingAs($user)->get(route('user.tickets.show', $booking->items()->firstOrFail()))->assertNotFound();
});

it('does not allow combo changes after payment has started', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'combo-pending-1');
    $booking->transitionTo(BookingStatus::PendingPayment);
    $booking->save();
    $concession = Concession::query()->create(['name' => 'Combo', 'sku' => 'COMBO-PENDING', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);

    expect(fn () => app(AddConcessions::class)->execute($booking, [$concession->id => 1]))->toThrow(RuntimeException::class);
    expect($concession->refresh()->stock)->toBe(3);
});

it('treats combo quantities as the selected final quantity', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'combo-quantity-1');
    $concession = Concession::query()->create(['name' => 'Combo', 'sku' => 'COMBO-QUANTITY', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);

    app(AddConcessions::class)->execute($booking, [$concession->id => 2]);
    app(AddConcessions::class)->execute($booking, [$concession->id => 1]);

    expect($booking->refresh()->total_minor_units)->toBe(150000)
        ->and($booking->concessions()->firstOrFail()->quantity)->toBe(1)
        ->and($concession->refresh()->stock)->toBe(2);
});

it('returns combo stock when a held booking is cancelled', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'combo-cancel-1');
    $concession = Concession::query()->create(['name' => 'Combo', 'sku' => 'COMBO-CANCEL', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);
    app(AddConcessions::class)->execute($booking, [$concession->id => 1]);

    app(CancelBooking::class)->execute($booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)->and($concession->refresh()->stock)->toBe(3);
});

it('redirects an expired checkout instead of returning a server error', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $user = User::factory()->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'expired-checkout-1');
    $booking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();

    $this->actingAs($user)->get(route('user.bookings.checkout', $booking))
        ->assertRedirect(route('user.bookings.show', $booking))
        ->assertSessionHasErrors('booking');
});

it('rejects a hold inside the minimum lead time', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addMinutes(5)->toDateTimeString(), now()->addMinutes(125)->toDateTimeString(), 100000);

    expect(fn () => app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'minimum-lead-1'))->toThrow(SeatHoldConflict::class);
});

it('claims an outbox message only once when the publisher runs repeatedly', function (): void {
    Queue::fake();
    $message = OutboxMessage::query()->create([
        'aggregate_type' => Booking::class,
        'aggregate_id' => 1,
        'event_type' => 'booking.created',
        'payload' => ['booking_id' => 1],
        'available_at' => now()->subMinute(),
    ]);

    $this->artisan('app:outbox-publish')->assertExitCode(0);
    $this->artisan('app:outbox-publish')->assertExitCode(0);

    Queue::assertPushed(PublishOutboxMessage::class, 1);
    expect($message->refresh()->claimed_at)->not->toBeNull();
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

it('calculates refunded revenue through the polymorphic payment relation', function (): void {
    $booking = Booking::factory()->create([
        'created_at' => now()->subDay(),
        'amount_minor_units' => 150000,
        'total_minor_units' => 150000,
    ]);

    $booking->payment()->create([
        'provider' => 'fake',
        'status' => 'refunded',
        'amount_minor_units' => 150000,
        'currency' => 'VND',
    ]);

    $summary = app(BookingReport::class)->summary(CarbonImmutable::now()->subDays(2), CarbonImmutable::now());

    expect($summary['refunded_minor_units'])->toBe(150000);
});
