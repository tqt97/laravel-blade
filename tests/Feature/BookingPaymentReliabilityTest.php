<?php

use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Actions\Movie\Booking\HoldSeats;
use App\Actions\Movie\Booking\PayBooking;
use App\Actions\Movie\Booking\RecoverStuckPayment;
use App\Actions\Movie\Catalog\CreateScreening;
use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusRetriever;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Jobs\ProcessStripeWebhook;
use App\Jobs\ReconcilePayment;
use App\Models\Movie\Booking;
use App\Models\Movie\Movie;
use App\Models\Movie\ScreeningRoom;
use App\Models\Movie\Seat;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\ProviderPaymentStatus;
use App\Support\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

it('uses a new immutable provider idempotency key for every payment retry', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'payment-retry-key');
    $gateway = new class implements PaymentGateway
    {
        public function charge(Payment $payment): PaymentResult
        {
            return new PaymentResult('failed', failureMessage: 'Card declined.');
        }

        public function refund(Payment $payment): PaymentResult
        {
            return new PaymentResult('refunded', 'refund_retry_key');
        }
    };
    app()->instance(PaymentGateway::class, $gateway);

    app(PayBooking::class)->execute($booking);
    app(PayBooking::class)->execute($booking->refresh());

    $keys = $booking->payment->attempts()->orderBy('id')->pluck('attempt_key');

    expect($keys)->toHaveCount(2)
        ->and($keys->unique())->toHaveCount(2)
        ->and($keys->every(fn (string $key): bool => str_starts_with($key, 'booking-payment-')))->toBeTrue();
});

it('does not finalize a provider success that has no provider payment id', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'payment-without-provider-id');
    app()->instance(PaymentGateway::class, new class implements PaymentGateway
    {
        public function charge(Payment $payment): PaymentResult
        {
            return new PaymentResult('succeeded');
        }

        public function refund(Payment $payment): PaymentResult
        {
            return new PaymentResult('refunded', 'refund_test');
        }
    });

    $payment = app(PayBooking::class)->execute($booking);

    expect($payment->status)->toBe(PaymentStatus::Unknown)
        ->and($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->items()->firstOrFail()->status)->toBe(TicketStatus::Issued)
        ->and($payment->attempts()->latest('id')->firstOrFail()->status)->toBe(PaymentAttemptStatus::Unknown);
});

it('restores an orphaned provider id and dispatches reconciliation', function (): void {
    Queue::fake();
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'orphan-provider-id');
    $payment = $booking->payment()->create([
        'provider' => 'stripe',
        'status' => PaymentStatus::Processing,
        'amount_minor_units' => $booking->amount_minor_units,
        'currency' => $booking->currency,
        'processing_started_at' => now()->subMinutes(30),
    ]);
    $attempt = $payment->attempts()->create([
        'attempt_key' => 'orphan-provider-attempt',
        'status' => PaymentAttemptStatus::Succeeded,
        'provider_payment_id' => 'pi_orphaned',
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
        'started_at' => now()->subMinutes(30),
    ]);

    expect(app(RecoverStuckPayment::class)->execute($payment))->toBeTrue()
        ->and($payment->refresh()->provider_payment_id)->toBe($attempt->provider_payment_id)
        ->and($payment->status)->toBe(PaymentStatus::Pending);
    Queue::assertPushed(ReconcilePayment::class, fn (ReconcilePayment $job): bool => $job->paymentId === $payment->id);
});

it('dispatches reconciliation for orphaned provider ids found by the command', function (): void {
    Queue::fake();
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999999,
        'provider' => 'stripe',
        'status' => PaymentStatus::Pending,
        'amount_minor_units' => 100000,
        'currency' => 'VND',
    ]);
    $payment->attempts()->create([
        'attempt_key' => 'command-orphan-provider-attempt',
        'status' => PaymentAttemptStatus::Succeeded,
        'provider_payment_id' => 'pi_command_orphan',
        'amount_minor_units' => 100000,
        'currency' => 'VND',
    ]);

    $this->artisan('payments:reconcile')->assertExitCode(0);

    Queue::assertPushed(ReconcilePayment::class, fn (ReconcilePayment $job): bool => $job->paymentId === $payment->id);
});

it('marks reconciliation unknown when provider amount or metadata does not match', function (): void {
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999999,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_reconcile_mismatch',
        'status' => PaymentStatus::Processing,
        'amount_minor_units' => 100000,
        'currency' => 'VND',
    ]);
    $payment->attempts()->create([
        'attempt_key' => 'reconcile-mismatch-attempt',
        'status' => PaymentAttemptStatus::Processing,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
    ]);
    $retriever = new class implements PaymentStatusRetriever
    {
        public function retrieve(string $providerPaymentId): ProviderPaymentStatus
        {
            return new ProviderPaymentStatus('succeeded', $providerPaymentId, [
                'amount_received' => 100001,
                'currency' => 'vnd',
                'metadata' => ['payable_id' => '999999', 'payable_type' => Booking::class],
            ]);
        }

        public function retrieveByAttemptKey(string $attemptKey): ProviderPaymentStatus
        {
            return new ProviderPaymentStatus('unknown');
        }
    };

    (new ReconcilePayment($payment->id))->handle($retriever, app(FinalizeSuccessfulPayment::class));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Unknown)
        ->and($payment->attempts()->latest('id')->firstOrFail()->status)->toBe(PaymentAttemptStatus::Unknown);
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
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Processing)
        ->and($payment->refresh()->reconciliation_attempts)->toBe(1);
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

it('creates a Payment Element intent with a durable reconciliation key', function (): void {
    Http::fake([
        'https://api.stripe.com/v1/payment_intents' => Http::response([
            'id' => 'pi_payment_element',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_payment_element_secret',
        ], 200),
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
    $attemptKey = 'booking-payment-'.$payment->id.'-1';
    $payment->attempts()->create([
        'attempt_key' => $attemptKey,
        'status' => PaymentAttemptStatus::Processing,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
    ]);

    $result = app(StripePaymentGateway::class)->charge($payment);

    expect($result->status)->toBe('requires_action')
        ->and($result->metadata['client_secret'])->toBe('pi_payment_element_secret');
    Http::assertSent(function ($request) use ($attemptKey): bool {
        $body = $request->body();

        return ! str_contains($body, 'confirm=true')
            && str_contains($body, 'metadata%5Battempt_key%5D='.$attemptKey)
            && str_contains($body, 'payment_method_types%5B0%5D=card');
    });
});

it('reconciles a timeout payment by searching Stripe with its attempt key', function (): void {
    Http::fake([
        'https://api.stripe.com/v1/payment_intents/search*' => Http::response([
            'data' => [[
                'id' => 'pi_recovered',
                'status' => 'processing',
                'amount' => 100000,
                'amount_received' => 0,
                'currency' => 'vnd',
                'metadata' => [
                    'payable_id' => '999999',
                    'payable_type' => Booking::class,
                    'attempt_key' => 'timeout-lookup',
                ],
            ]],
        ], 200),
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
        'attempt_key' => 'timeout-lookup',
        'status' => PaymentAttemptStatus::Unknown,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
    ]);

    (new ReconcilePayment($payment->id))->handle(new StripePaymentGateway, app(FinalizeSuccessfulPayment::class));

    expect($payment->refresh()->provider_payment_id)->toBe('pi_recovered')
        ->and($payment->status)->toBe(PaymentStatus::Pending);
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

    $response->assertStatus(400);
    expect(PaymentWebhookEvent::query()->where('event_id', 'evt_missing_provider_id')->firstOrFail()->failed_at)->not->toBeNull();
});

it('persists a signed webhook as an orphan when its payment is not local yet', function (): void {
    Queue::fake();
    $payload = ['id' => 'evt_orphan_payment', 'type' => 'payment_intent.succeeded', 'data' => ['object' => [
        'id' => 'pi_orphan_webhook',
        'amount_received' => 100000,
        'currency' => 'vnd',
        'metadata' => ['payable_id' => '999999', 'payable_type' => Booking::class],
    ]]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $response = $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertStatus(202);
    expect(PaymentWebhookEvent::query()->where('event_id', 'evt_orphan_payment')->firstOrFail())
        ->provider_payment_id->toBe('pi_orphan_webhook')
        ->orphaned_at->not->toBeNull();
    Queue::assertPushed(ProcessStripeWebhook::class, fn (ProcessStripeWebhook $job): bool => $job->eventId === 'evt_orphan_payment');
});

it('returns bad request for malformed signed Stripe webhook JSON', function (): void {
    $body = '{"id":"evt_invalid_json"';
    $timestamp = time();
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(400);
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
    $payload = ['id' => 'evt_old_processing', 'created' => 100, 'type' => 'payment_intent.processing', 'data' => ['object' => ['id' => 'pi_monotonic', 'amount' => 100000, 'currency' => 'vnd', 'metadata' => ['payable_id' => '999999', 'payable_type' => Booking::class]]]];
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
