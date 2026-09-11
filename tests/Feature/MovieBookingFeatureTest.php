<?php

use App\Actions\Movie\Booking\ApplyCoupon;
use App\Actions\Movie\Booking\CancelBooking;
use App\Actions\Movie\Booking\EditBookingSelection;
use App\Actions\Movie\Booking\ExpireBooking;
use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Actions\Movie\Booking\HoldSeats;
use App\Actions\Movie\Booking\PayBooking;
use App\Actions\Movie\Booking\RefundBooking;
use App\Actions\Movie\Catalog\CreateScreening;
use App\Actions\Movie\Concessions\AddConcessions;
use App\Actions\Movie\Ticketing\CheckInTicket;
use App\Contracts\PaymentGateway;
use App\Enums\Infrastructure\OutboxEventType;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\RefundAttemptStatus;
use App\Jobs\PublishOutboxMessage;
use App\Mail\BookingConfirmationMail;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Inventory\InventoryMovement;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Models\Movie\Coupon;
use App\Models\Movie\CouponReservation;
use App\Models\Movie\Movie;
use App\Models\Movie\ScreeningRoom;
use App\Models\Movie\Seat;
use App\Models\Payments\Payment;
use App\Models\User;
use App\Notifications\MovieBookingNotification;
use App\Queries\Movie\BookingReport;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use App\Support\Booking\SeatHoldConflict;
use App\Support\Cinema\TicketQrCode;
use App\Support\Payment\PaymentResult;
use App\Support\Time\BookingClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('lets guests browse movies and seats before requiring authentication to hold them', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);

    $this->get(route('cinema.movies.index'))->assertOk()->assertSee($screening->movie->title);
    $this->get(route('cinema.movies.show', $screening->movie))->assertOk()->assertSee($room->name);
    $this->get(route('cinema.screenings.show', [$screening->movie, $screening]))->assertOk()->assertSee((string) $seat->seat_number);
    $this->post(route('user.screenings.hold', $screening), ['seat_ids' => [$seat->id], 'idempotency_key' => 'guest-hold'])->assertRedirect(route('login'));
});

it('serves an SEO sitemap with active movie and screening URLs', function (): void {
    $room = ScreeningRoom::factory()->create();
    $movie = Movie::factory()->create();
    $screening = app(CreateScreening::class)->execute($movie, $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);

    $this->get(route('seo.sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('cinema.movies.show', $movie), false)
        ->assertSee(route('cinema.screenings.show', [$movie, $screening]), false);
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

it('keeps a freshly held booking valid through checkout in the configured timezone', function (): void {
    $previousTimezone = config('app.timezone');
    config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10 10:00:00', 'Asia/Ho_Chi_Minh'));

    try {
        $room = ScreeningRoom::factory()->create(['timezone' => 'Asia/Ho_Chi_Minh']);
        $seat = Seat::factory()->for($room, 'room')->create();
        $screening = app(CreateScreening::class)->execute(
            Movie::factory()->create(),
            $room,
            '2026-09-10 11:00:00',
            '2026-09-10 13:00:00',
            100000,
        );
        $user = User::factory()->create();
        $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'checkout-timezone');

        $this->actingAs($user)
            ->get(route('user.bookings.checkout', $booking))
            ->assertOk()
            ->assertViewIs('user.bookings.checkout')
            ->assertSee('data-expires-at="2026-09-10T10:10:00+07:00"', false);

        expect($booking->refresh()->expires_at?->isFuture())->toBeTrue();
    } finally {
        CarbonImmutable::setTestNow();
        config(['app.timezone' => $previousTimezone]);
    }
});

it('keeps the current booking when an edited seat cannot be held', function (): void {
    $room = ScreeningRoom::factory()->create();
    $oldSeat = Seat::factory()->for($room, 'room')->create(['seat_number' => 1]);
    $newSeat = Seat::factory()->for($room, 'room')->create(['seat_number' => 2]);
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($user, $screening, [$oldSeat->id], 'edit-atomic-old');
    app(HoldSeats::class)->execute($otherUser, $screening, [$newSeat->id], 'edit-atomic-new');

    expect(fn () => app(EditBookingSelection::class)->execute($user, $screening, [$newSeat->id], 'edit-atomic-request'))
        ->toThrow(SeatHoldConflict::class);
    expect($booking->refresh()->status)->toBe(BookingStatus::Held)
        ->and($booking->items()->firstOrFail()->screeningSeat->seat_id)->toBe($oldSeat->id);
});

it('records and clears the booking owner for a held seat', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'seat-owner');
    $screeningSeat = $booking->items()->firstOrFail()->screeningSeat;

    expect($screeningSeat->held_by_booking_id)->toBe($booking->id);

    app(PayBooking::class)->execute($booking);

    expect($screeningSeat->refresh()->held_by_booking_id)->toBeNull();
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

it('replaces a held booking when the user edits the selected seats', function (): void {
    $room = ScreeningRoom::factory()->create();
    $firstSeat = Seat::factory()->for($room, 'room')->create(['seat_number' => 1]);
    $secondSeat = Seat::factory()->for($room, 'room')->create(['seat_number' => 2]);
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('user.screenings.hold', $screening), [
        'seat_ids' => [$firstSeat->id],
        'idempotency_key' => 'edit-seat-first',
    ])->assertRedirect();
    $oldBooking = $user->bookings()->latest('id')->firstOrFail();

    $this->actingAs($user)->post(route('user.screenings.hold', $screening), [
        'seat_ids' => [$secondSeat->id],
        'idempotency_key' => 'edit-seat-second',
    ])->assertRedirect();
    $newBooking = $user->bookings()->latest('id')->firstOrFail();

    expect($newBooking->is($oldBooking))->toBeFalse()
        ->and($oldBooking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($newBooking->items()->firstOrFail()->screeningSeat->seat_id)->toBe($secondSeat->id);
});

it('preserves a guest seat selection through login before creating the hold', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();

    $this->post(route('cinema.screenings.hold', [$screening->movie, $screening]), ['seat_ids' => [$seat->id], 'idempotency_key' => 'resume-hold'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('cinema.pending_hold.seat_ids', [$seat->id]);

    $response = $this->actingAs($user)->get(route('user.cinema.hold.resume'));
    $booking = $user->bookings()->where('screening_id', $screening->id)->firstOrFail();

    $response->assertRedirect(route('cinema.screenings.show', [$screening->movie, $screening]));
});

it('resumes guest seats and combos after the real login redirect', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $concession = Concession::query()->create(['name' => 'Login Popcorn', 'sku' => 'LOGIN-POPCORN', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 5, 'is_active' => true]);
    $user = User::factory()->create(['email' => 'resume@example.com']);

    $this->post(route('cinema.screenings.hold', [$screening->movie, $screening]), [
        'seat_ids' => [$seat->id],
        'idempotency_key' => 'resume-with-combo',
        'quantities' => [$concession->id => 1],
    ])->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('user.cinema.hold.resume'));

    $this->get(route('user.cinema.hold.resume'))
        ->assertRedirect(route('cinema.screenings.show', [$screening->movie, $screening]));

    $this->get(route('cinema.screenings.show', [$screening->movie, $screening]))
        ->assertOk()
        ->assertSee('data-seat-selected="true"', false)
        ->assertSee('Login Popcorn')
        ->assertSee('value="1"', false);

    $this->post(route('cinema.screenings.hold', [$screening->movie, $screening]), [
        'seat_ids' => [$seat->id],
        'idempotency_key' => $user->bookings()->firstOrFail()->idempotency_key,
        'quantities' => [$concession->id => 1],
    ])->assertRedirect(route('user.bookings.checkout', $user->bookings()->firstOrFail()));

    expect($user->bookings()->count())->toBe(1)
        ->and($concession->refresh()->stock)->toBe(4);
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
        ->assertRedirect(route('cinema.screenings.show', [$screening->movie, $screening]));
    $this->actingAs($user)->get(route('user.bookings.checkout', $booking))->assertOk();
    $concession = Concession::query()->create(['name' => 'Large Popcorn', 'sku' => 'FLOW-POPCORN', 'price_minor_units' => 75000, 'currency' => 'VND', 'stock' => 10, 'is_active' => true]);
    $this->actingAs($user)->post(route('user.screenings.hold', $screening), [
        'seat_ids' => [$seat->id],
        'idempotency_key' => 'checkout-flow',
        'quantities' => [$concession->id => 1],
    ])->assertRedirect(route('user.bookings.checkout', $booking));
    $this->actingAs($user)->get(route('user.bookings.combos', $booking))->assertOk()->assertSee('Large Popcorn');
    $this->actingAs($user)->get(route('user.bookings.checkout', $booking))->assertOk()->assertSee('Large Popcorn')->assertDontSee('data-combo-increase');

    $paymentResponse = $this->actingAs($user)->post(route('user.bookings.pay', $booking), ['quantities' => [$concession->id => 1]]);

    $paymentResponse->assertRedirect(route('user.bookings.success', $booking));
    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->concessions()->firstOrFail()->quantity)->toBe(1)
        ->and($concession->refresh()->stock)->toBe(9);
    $this->actingAs($user)->get(route('user.bookings.success', $booking))
        ->assertOk()
        ->assertSee('TKT-')
        ->assertSee('Large Popcorn')
        ->assertSee('175,000 VND');
    $this->actingAs($user)->get(route('user.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Large Popcorn')
        ->assertSee('175,000 VND');
});

it('rechecks combo stock atomically when paying and exposes live availability', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'combo-payment-stock');
    $concession = Concession::query()->create(['name' => 'Limited Combo', 'sku' => 'COMBO-LIMITED', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 0, 'is_active' => true]);

    $this->actingAs($user)->get(route('user.bookings.combo-availability', $booking))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath("concessions.{$concession->id}.stock", 0)
        ->assertJsonPath("concessions.{$concession->id}.max", 0);

    $this->actingAs($user)->post(route('user.bookings.pay', $booking), ['quantities' => [$concession->id => 1]])
        ->assertSessionHasErrors('quantities');

    expect($booking->refresh()->status)->toBe(BookingStatus::Held)
        ->and($booking->payment)->toBeNull()
        ->and($concession->refresh()->stock)->toBe(0);
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

it('does not restore combo stock twice when a successful refund is retried', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'refund-idempotency');
    $concession = Concession::query()->create(['name' => 'Refund Combo', 'sku' => 'COMBO-REFUND-IDEMPOTENT', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);
    app(AddConcessions::class)->execute($booking, [$concession->id => 1]);
    app(PayBooking::class)->execute($booking);

    app(RefundBooking::class)->execute($booking);
    app(RefundBooking::class)->execute($booking);

    expect($concession->refresh()->stock)->toBe(3)
        ->and($booking->payment()->firstOrFail()->status)->toBe(PaymentStatus::Refunded)
        ->and(InventoryMovement::query()->where('idempotency_key', 'payment-refund-'.$booking->payment->id.'-'.$concession->id)->count())->toBe(1);
});

it('retries an unknown refund with the same logical operation and finalizes it once', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'refund-retry');
    app(PayBooking::class)->execute($booking);
    $gateway = new class implements PaymentGateway
    {
        public int $calls = 0;

        public function charge(Payment $payment): PaymentResult
        {
            return new PaymentResult('succeeded', 'unused');
        }

        public function refund(Payment $payment): PaymentResult
        {
            $this->calls++;
            if ($this->calls === 1) {
                throw new RuntimeException('temporary refund timeout');
            }

            return new PaymentResult('refunded', 're_123');
        }
    };
    app()->instance(PaymentGateway::class, $gateway);

    app(RefundBooking::class)->execute($booking);
    expect($booking->payment()->firstOrFail()->refundAttempts()->latest('id')->firstOrFail()->status)->toBe(RefundAttemptStatus::Unknown);

    app(RefundBooking::class)->execute($booking);

    expect($gateway->calls)->toBe(2)
        ->and($booking->payment()->firstOrFail()->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled);
});

it('finalizes successful Stripe webhooks idempotently', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'stripe-webhook-1');
    $payment = $booking->payment()->create(['provider' => 'stripe', 'provider_payment_id' => 'pi_webhook_1', 'status' => PaymentStatus::Pending, 'amount_minor_units' => $booking->amount_minor_units, 'currency' => 'VND']);
    $payload = ['id' => 'evt_webhook_1', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => $payment->provider_payment_id, 'amount_received' => $payment->amount_minor_units, 'currency' => 'vnd', 'metadata' => ['payable_id' => (string) $booking->id, 'payable_type' => Booking::class]]]];
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
    expect($payment->refresh()->attempts()->latest('id')->firstOrFail()->status)->toBe(PaymentAttemptStatus::Succeeded);
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
    app(AddConcessions::class)->execute($booking, [$concession->id => 1]);
    expect($booking->refresh()->total_minor_units)->toBe(150000)->and($concession->refresh()->stock)->toBe(2);
    $booking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();
    $screeningSeat = $screening->screeningSeats()->firstOrFail();
    $screeningSeat->forceFill(['held_until' => now()->subMinute()])->saveQuietly();
    expect($screeningSeat->refresh()->isAvailableForSelection())->toBeTrue();
    app(ExpireBooking::class)->execute($booking);
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)->and($screeningSeat->refresh()->status)->toBe(ScreeningSeatStatus::Available)->and($concession->refresh()->stock)->toBe(3);
    $this->actingAs($user)->get(route('user.tickets.show', $booking->items()->firstOrFail()))->assertNotFound();
});

it('notifies the user once when a seat hold expires', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'expiry-notification');
    $booking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();

    app(ExpireBooking::class)->execute($booking);
    app(ExpireBooking::class)->execute($booking);
    $expiredEvent = OutboxMessage::query()
        ->where('aggregate_id', $booking->id)
        ->where('event_type', 'booking.expired')
        ->firstOrFail();
    (new PublishOutboxMessage($expiredEvent->id))->handle();

    expect($user->notifications()->where('data->key', 'booking_expired:'.$booking->id)->count())->toBe(1)
        ->and(OutboxMessage::query()->where('aggregate_id', $booking->id)->where('event_type', 'booking.expired')->count())->toBe(1);
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
    $secondSeat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id, $secondSeat->id], 'combo-quantity-1');
    $concession = Concession::query()->create(['name' => 'Combo', 'sku' => 'COMBO-QUANTITY', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);

    app(AddConcessions::class)->execute($booking, [$concession->id => 2]);
    app(AddConcessions::class)->execute($booking, [$concession->id => 1]);

    expect($booking->refresh()->total_minor_units)->toBe(250000)
        ->and($booking->concessions()->firstOrFail()->quantity)->toBe(1)
        ->and($concession->refresh()->stock)->toBe(2);
});

it('releases all combos when a same-seat edit submits an empty replacement payload', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'combo-empty-edit');
    $concession = Concession::query()->create(['name' => 'Empty Edit Combo', 'sku' => 'COMBO-EMPTY-EDIT', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);

    app(AddConcessions::class)->execute($booking, [$concession->id => 1]);
    app(EditBookingSelection::class)->execute($user, $screening, [$seat->id], 'combo-empty-edit-retry', []);

    expect($booking->refresh()->concessions)->toHaveCount(0)
        ->and($concession->refresh()->stock)->toBe(3);
});

it('rejects combo mutation after a booking hold expires before scheduler cleanup', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'expired-mutation');
    $concession = Concession::query()->create(['name' => 'Expired Mutation Combo', 'sku' => 'COMBO-EXPIRED-MUTATION', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);
    $booking->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(fn () => app(AddConcessions::class)->execute($booking, [$concession->id => 1]))->toThrow(RuntimeException::class);
    expect($concession->refresh()->stock)->toBe(3)
        ->and($booking->refresh()->concessions)->toHaveCount(0);
});

it('treats omitted combo quantities as zero and releases their stock', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'combo-replacement');
    $first = Concession::query()->create(['name' => 'First Combo', 'sku' => 'COMBO-REPLACEMENT-1', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);
    $second = Concession::query()->create(['name' => 'Second Combo', 'sku' => 'COMBO-REPLACEMENT-2', 'price_minor_units' => 75000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);

    app(AddConcessions::class)->execute($booking, [$first->id => 1, $second->id => 1]);
    app(AddConcessions::class)->execute($booking, [$first->id => 1]);

    expect($booking->refresh()->concessions()->pluck('concession_id')->all())->toBe([$first->id])
        ->and($first->refresh()->stock)->toBe(2)
        ->and($second->refresh()->stock)->toBe(3);
});

it('releases an inactive combo when it is omitted from a replacement payload', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'combo-inactive-replacement');
    $concession = Concession::query()->create(['name' => 'Inactive Combo', 'sku' => 'COMBO-INACTIVE-REPLACEMENT', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 3, 'is_active' => true]);

    app(AddConcessions::class)->execute($booking, [$concession->id => 1]);
    $concession->forceFill(['is_active' => false])->save();
    app(AddConcessions::class)->execute($booking, []);

    expect($booking->refresh()->concessions)->toHaveCount(0)
        ->and($concession->refresh()->stock)->toBe(3);
});

it('limits total combo quantity to three times the held tickets', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'combo-ticket-limit');
    $concession = Concession::query()->create(['name' => 'Ticket Limited Combo', 'sku' => 'COMBO-TICKET-LIMIT', 'price_minor_units' => 50000, 'currency' => 'VND', 'stock' => 5, 'is_active' => true]);

    expect(fn () => app(AddConcessions::class)->execute($booking, [$concession->id => 4]))
        ->toThrow(RuntimeException::class);
    expect($concession->refresh()->stock)->toBe(5)
        ->and($booking->refresh()->concessions)->toHaveCount(0);
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

it('shows a dedicated expired checkout screen with a reselection action', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $user = User::factory()->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'expired-checkout-1');
    $booking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();

    $this->actingAs($user)->get(route('user.bookings.checkout', $booking))
        ->assertOk()
        ->assertViewIs('user.bookings.expired')
        ->assertSee('Your seat hold has expired')
        ->assertSee(route('cinema.screenings.show', [$screening->movie, $screening]));
});

it('sends an expired checkout to the movie list when the showtime has started', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $now = CarbonImmutable::now('UTC');
    $start = $now->addDay();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, $start->toDateTimeString(), $start->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'expired-after-start');

    $screening->forceFill([
        'starts_at' => $now->subMinutes(6),
        'ends_at' => $now->addHours(2),
    ])->saveQuietly();
    $booking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();

    $this->actingAs($booking->user)->get(route('user.bookings.checkout', $booking))
        ->assertOk()
        ->assertSee('This showtime has expired')
        ->assertSee(route('cinema.movies.index'))
        ->assertDontSee(route('cinema.screenings.show', [$screening->movie, $screening]));
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

it('does not send the same outbox email twice when the delivery job is retried', function (): void {
    Mail::fake();
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'outbox-delivery-idempotency');
    $message = OutboxMessage::query()->where('aggregate_id', $booking->id)->where('event_type', 'booking.created')->latest('id')->firstOrFail();
    $job = new PublishOutboxMessage($message->id);

    $job->handle();
    $job->handle();

    Mail::assertNothingSent();
    expect($message->refresh()->published_at)->not->toBeNull()
        ->and($message->deliveries()->where('channel', 'booking-created')->where('status', 'sent')->count())->toBe(1)
        ->and($message->deliveries()->where('channel', 'booking-created')->value('idempotency_key'))->toBe('booking.created:'.$booking->id)
        ->and($message->deliveries()->where('channel', 'booking-created')->value('attempts'))->toBe(1);
});

it('checks in a paid ticket once inside the configured screening window', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $start = now()->addDay()->startOfHour();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, $start->toDateTimeString(), $start->copy()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'cinema-checkin-1');
    app(PayBooking::class)->execute($booking);
    $item = $booking->items()->firstOrFail();
    CarbonImmutable::setTestNow(BookingClock::parseStored((string) $screening->getRawOriginal('starts_at'))?->subMinutes(30));
    app(CheckInTicket::class)->execute($item->ticket_code, User::factory()->create(['is_admin' => true])->id);
    expect($item->refresh()->status)->toBe(TicketStatus::CheckedIn);
    CarbonImmutable::setTestNow();
});

it('rejects check-in while a refund has claimed the booking payment', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $start = now()->addDay()->startOfHour();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, $start->toDateTimeString(), $start->copy()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'cinema-refund-checkin-race');
    app(PayBooking::class)->execute($booking);
    $payment = $booking->payment()->firstOrFail();
    $payment->forceFill(['status' => PaymentStatus::Refunding])->save();
    $item = $booking->items()->firstOrFail();
    CarbonImmutable::setTestNow(BookingClock::parseStored((string) $screening->getRawOriginal('starts_at'))?->subMinutes(30));

    expect(fn () => app(CheckInTicket::class)->execute($item->ticket_code, User::factory()->create(['is_admin' => true])->id))
        ->toThrow(BookingOperationFailed::class)
        ->and($item->refresh()->status)->toBe(TicketStatus::Issued);

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

it('exposes expired held seats as available to the live availability endpoint', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'availability-expired-hold');
    $screeningSeat = $screening->screeningSeats()->firstOrFail();
    $screeningSeat->forceFill(['held_until' => now()->subMinute()])->save();

    $response = $this->getJson(route('cinema.screenings.availability', [$screening->movie, $screening]));

    $response->assertOk()->assertJsonPath('seats.'.$seat->id.'.available', true)
        ->assertJsonPath('seats.'.$seat->id.'.owned_by_current_booking', false);
    expect($booking->refresh()->status)->toBe(BookingStatus::Held);
});

it('reports the current users held seats as server-owned availability state', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'availability-owned-hold');

    $this->actingAs($user)
        ->getJson(route('cinema.screenings.availability', [$screening->movie, $screening]))
        ->assertOk()
        ->assertJsonPath('seats.'.$seat->id.'.available', false)
        ->assertJsonPath('seats.'.$seat->id.'.owned_by_current_booking', true);
});

it('prevents a second user from acquiring a seat already held by another user', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'concurrent-owner');

    expect(fn () => app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'concurrent-contender'))
        ->toThrow(SeatHoldConflict::class);
});

it('reserves a valid coupon and updates the booking total', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create(['price_minor_units' => 100000]);
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'coupon-apply');
    $coupon = Coupon::factory()->create(['code' => 'SAVE20', 'type' => 'percentage', 'value' => 20]);

    app(ApplyCoupon::class)->execute($booking, ' save20 ');

    expect($booking->refresh()->discount_minor_units)->toBe(20000)
        ->and($booking->total_minor_units)->toBe(80000)
        ->and($coupon->refresh()->used_count)->toBe(1)
        ->and(CouponReservation::query()->where('booking_id', $booking->id)->count())->toBe(1);
});

it('reuses a released reservation when a coupon is applied again', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create(['price_minor_units' => 100000]);
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'coupon-reapply');
    $first = Coupon::factory()->create(['code' => 'FIRST10', 'value' => 10000]);
    $second = Coupon::factory()->create(['code' => 'SECOND10', 'value' => 10000]);

    app(ApplyCoupon::class)->execute($booking, $first->code);
    app(ApplyCoupon::class)->execute($booking, $second->code);
    app(ApplyCoupon::class)->execute($booking, $first->code);

    expect($booking->refresh()->coupon_id)->toBe($first->id)
        ->and($booking->couponReservations()->where('coupon_id', $first->id)->count())->toBe(1)
        ->and($booking->couponReservations()->where('status', 'reserved')->count())->toBe(1);
});

it('creates an in-app notification when a booking payment succeeds', function (): void {
    Mail::fake();
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'notification-success');
    app(PayBooking::class)->execute($booking);
    $message = OutboxMessage::query()->where('aggregate_id', $booking->id)->where('event_type', OutboxEventType::BookingPaymentSucceeded)->firstOrFail();

    app(PublishOutboxMessage::class, ['outboxMessageId' => $message->id])->handle();

    Mail::assertSent(BookingConfirmationMail::class);

    expect($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->firstOrFail()->data['event'])->toBe('booking_confirmed');

    $notification = $user->notifications()->firstOrFail();
    $this->actingAs($user)->getJson(route('user.notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.id', $notification->getKey())
        ->assertJsonPath('notifications.0.url', '/user/bookings/'.$booking->getKey());
    $this->actingAs($user)->patchJson(route('user.notifications.read', $notification->getKey()))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
    $this->actingAs($user)->deleteJson(route('user.notifications.destroy', $notification->getKey()))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
    expect($user->notifications()->whereKey($notification->getKey())->exists())->toBeFalse();

    $user->notify(new MovieBookingNotification($booking, 'booking_confirmed'));
    $user->notify(new MovieBookingNotification($booking, 'booking_reminder'));

    $this->actingAs($user)->deleteJson(route('user.notifications.destroy-all'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
    expect($user->notifications()->count())->toBe(0);
});

it('keeps legacy local notification links after the app url changes host', function (): void {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => MovieBookingNotification::class,
        'data' => ['url' => 'http://localhost:8000/user/bookings/123'],
    ]);

    $this->actingAs($user)->getJson(route('user.notifications.index'))
        ->assertOk()
        ->assertJsonPath('notifications.0.id', $notification->getKey())
        ->assertJsonPath('notifications.0.url', '/user/bookings/123');
});

it('claims a two-hour movie reminder once and publishes its outbox event', function (): void {
    $now = CarbonImmutable::now(BookingClock::timezone())->startOfMinute();
    CarbonImmutable::setTestNow($now);
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, $now->addHours(2)->addSeconds(30)->toDateTimeString(), $now->addHours(4)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'notification-reminder');
    app(PayBooking::class)->execute($booking);

    $this->artisan('booking:send-reminders')->assertExitCode(0);
    $this->artisan('booking:send-reminders')->assertExitCode(0);

    expect($booking->refresh()->reminder_sent_at)->not->toBeNull()
        ->and(OutboxMessage::query()->where('aggregate_id', $booking->id)->where('event_type', OutboxEventType::BookingReminderDue)->count())->toBe(1);
    CarbonImmutable::setTestNow();
});

it('releases a coupon reservation when a held booking expires', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'coupon-release');
    $coupon = Coupon::factory()->create(['code' => 'RELEASE10', 'value' => 10000]);
    app(ApplyCoupon::class)->execute($booking, $coupon->code);

    app(CancelBooking::class)->execute($booking);

    expect($coupon->refresh()->used_count)->toBe(0)
        ->and($booking->couponReservations()->firstOrFail()->status->value)->toBe('released');
});

it('rolls back the guest resume when a stored combo is no longer available', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();

    $this->withSession(['cinema.pending_hold' => [
        'screening_id' => $screening->id,
        'seat_ids' => [$seat->id],
        'idempotency_key' => 'resume-invalid-combo',
        'quantities' => [999999 => 1],
    ]])->actingAs($user)->get(route('user.cinema.hold.resume'))
        ->assertRedirect(route('cinema.screenings.show', [$screening->movie, $screening]))
        ->assertSessionHasErrors('quantities');

    expect($user->bookings()->count())->toBe(0)
        ->and($screening->screeningSeats()->firstOrFail()->status)->toBe(ScreeningSeatStatus::Available);
});

it('releases an expired hold before creating the edited booking', function (): void {
    $room = ScreeningRoom::factory()->create();
    $oldSeat = Seat::factory()->for($room, 'room')->create(['seat_number' => 1]);
    $newSeat = Seat::factory()->for($room, 'room')->create(['seat_number' => 2]);
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    $oldBooking = app(HoldSeats::class)->execute($user, $screening, [$oldSeat->id], 'expired-edit-old');
    $oldBooking->forceFill(['expires_at' => now()->subMinute()])->saveQuietly();
    $oldBooking->items()->firstOrFail()->screeningSeat()->update(['held_until' => now()->subMinute()]);

    $newBooking = app(EditBookingSelection::class)->execute($user, $screening, [$newSeat->id], 'expired-edit-new');

    expect($oldBooking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($newBooking->id)->not->toBe($oldBooking->id)
        ->and($oldBooking->items()->firstOrFail()->screeningSeat->refresh()->status)->toBe(ScreeningSeatStatus::Available)
        ->and($newBooking->items()->firstOrFail()->screeningSeat->status)->toBe(ScreeningSeatStatus::Held);
});
