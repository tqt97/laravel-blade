<?php

use App\Actions\Movie\Booking\HoldSeats;
use App\Actions\Movie\Booking\PayBooking;
use App\Actions\Movie\Booking\RecoverStuckPayment;
use App\Actions\Movie\Catalog\CreateScreening;
use App\Contracts\PaymentGateway;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Movie\Booking;
use App\Models\Movie\Movie;
use App\Models\Movie\ScreeningRoom;
use App\Models\Movie\Seat;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('formats supported currencies from integer minor units', function (): void {
    expect(Money::fromMinorUnits(1250, 'USD')->format())->toBe('12.50 USD')
        ->and(Money::fromMinorUnits(250000, 'VND')->format())->toBe('250,000 VND')
        ->and(fn () => Money::fromMinorUnits(100, 'XXX')->format())->toThrow(InvalidArgumentException::class);
});

it('does not charge a payment again while a previous attempt is pending', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'payment-concurrency');
    $gateway = new class implements PaymentGateway
    {
        public int $charges = 0;

        public function charge(Payment $payment): PaymentResult
        {
            $this->charges++;

            return new PaymentResult('pending', 'pi_pending_once', ['client_secret' => 'secret']);
        }

        public function refund(Payment $payment): PaymentResult
        {
            return new PaymentResult('refunded');
        }
    };
    app()->instance(PaymentGateway::class, $gateway);

    expect(app(PayBooking::class)->execute($booking)->status)->toBe(PaymentStatus::Pending)
        ->and(app(PayBooking::class)->execute($booking)->status)->toBe(PaymentStatus::Pending)
        ->and($gateway->charges)->toBe(1)
        ->and($booking->payment->attempts()->firstOrFail()->status)->toBe(PaymentAttemptStatus::Processing);
});

it('recovers a payment claim that never received a provider id', function (): void {
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999999,
        'provider' => 'fake',
        'amount_minor_units' => 100000,
        'currency' => 'VND',
        'status' => PaymentStatus::Processing,
        'provider_payment_id' => null,
        'processing_started_at' => now()->subMinutes(30),
    ]);
    $attempt = $payment->attempts()->create([
        'attempt_key' => 'stuck-payment-attempt',
        'status' => PaymentAttemptStatus::Processing,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
        'started_at' => now()->subMinutes(30),
    ]);

    expect(app(RecoverStuckPayment::class)->execute($payment))->toBeTrue()
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Unknown)
        ->and($attempt->refresh()->status)->toBe(PaymentAttemptStatus::Unknown);
});

it('does not charge again when the first gateway response times out', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'payment-timeout');
    $gateway = new class implements PaymentGateway
    {
        public int $charges = 0;

        public function charge(Payment $payment): PaymentResult
        {
            $this->charges++;
            throw new RuntimeException('Gateway timeout');
        }

        public function refund(Payment $payment): PaymentResult
        {
            return new PaymentResult('refunded');
        }
    };
    app()->instance(PaymentGateway::class, $gateway);

    expect(app(PayBooking::class)->execute($booking)->status)->toBe(PaymentStatus::Unknown)
        ->and(app(PayBooking::class)->execute($booking)->status)->toBe(PaymentStatus::Unknown)
        ->and($gateway->charges)->toBe(1)
        ->and($booking->payment->attempts()->where('status', 'unknown')->count())->toBe(1);
});

it('uses the payment attempt key as the Stripe idempotency key', function (): void {
    Http::fake([
        'https://api.stripe.com/*' => Http::response(['id' => 'pi_attempt', 'status' => 'processing'], 200),
    ]);
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999999,
        'provider' => 'stripe',
        'amount_minor_units' => 100000,
        'currency' => 'VND',
        'status' => PaymentStatus::Processing,
        'attempts' => 1,
    ]);
    $payment->attempts()->create([
        'attempt_key' => 'booking-payment-'.$payment->id.'-1',
        'status' => PaymentAttemptStatus::Processing,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
        'started_at' => now()->utc(),
    ]);

    app(StripePaymentGateway::class)->charge($payment);

    Http::assertSent(fn ($request): bool => $request->header('Idempotency-Key')[0] === 'booking-payment-'.$payment->id.'-1');
});

it('rejects a Stripe webhook when amount or currency does not match', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'webhook-mismatch');
    $payment = $booking->payment()->create(['provider' => 'stripe', 'provider_payment_id' => 'pi_mismatch', 'status' => PaymentStatus::Pending, 'amount_minor_units' => $booking->amount_minor_units, 'currency' => 'VND']);
    $payload = ['id' => 'evt_mismatch', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => $payment->provider_payment_id, 'amount_received' => $payment->amount_minor_units + 1, 'currency' => 'usd', 'metadata' => ['payable_id' => (string) $booking->id]]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $response = $this->call('POST', route('webhooks.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'), 'CONTENT_TYPE' => 'application/json'], $body);

    $response->assertStatus(422);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(PaymentWebhookEvent::query()->where('event_id', 'evt_mismatch')->firstOrFail()->failed_at)->not->toBeNull();
});

it('rejects a signed Stripe webhook without a provider payment id', function (): void {
    $payload = ['id' => 'evt_missing_provider_id', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['amount_received' => 100000, 'currency' => 'vnd']]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $response = $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertStatus(422);
    expect(PaymentWebhookEvent::query()->where('event_id', 'evt_missing_provider_id')->firstOrFail()->failed_at)->not->toBeNull();
});

it('does not regress a succeeded payment when an older Stripe event arrives', function (): void {
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999999,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_monotonic',
        'status' => PaymentStatus::Succeeded,
        'amount_minor_units' => 100000,
        'currency' => 'VND',
        'metadata' => ['stripe_last_event_created' => 200],
    ]);
    $payload = ['id' => 'evt_old_processing', 'created' => 100, 'type' => 'payment_intent.processing', 'data' => ['object' => ['id' => 'pi_monotonic', 'amount' => 100000, 'currency' => 'vnd', 'metadata' => []]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $response = $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertNoContent();
    expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('renders the user dashboard when upcoming bookings are joined to screenings', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'dashboard-upcoming');

    $this->actingAs($user)->get(route('user.dashboard'))->assertOk()->assertSee($screening->movie->title);
});
