<?php

use App\Actions\Booking\HoldSeats;
use App\Actions\Booking\PayBooking;
use App\Actions\Cinema\CreateScreening;
use App\Contracts\PaymentGateway;
use App\Enums\Payment\PaymentStatus;
use App\Models\Cinema\Movie;
use App\Models\Cinema\ScreeningRoom;
use App\Models\Cinema\Seat;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Payment\PaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->and($gateway->charges)->toBe(1);
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

    expect(app(PayBooking::class)->execute($booking)->status)->toBe(PaymentStatus::Processing)
        ->and(app(PayBooking::class)->execute($booking)->status)->toBe(PaymentStatus::Processing)
        ->and($gateway->charges)->toBe(1)
        ->and($booking->payment->attempts()->where('status', 'unknown')->count())->toBe(1);
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

it('renders the user dashboard when upcoming bookings are joined to screenings', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $user = User::factory()->create();
    app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'dashboard-upcoming');

    $this->actingAs($user)->get(route('user.dashboard'))->assertOk()->assertSee($screening->movie->title);
});
