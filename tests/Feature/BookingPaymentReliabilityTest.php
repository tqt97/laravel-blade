<?php

use App\Actions\Booking\Checkout\HoldSeats;
use App\Actions\Booking\Checkout\PayBooking;
use App\Actions\Booking\Payment\FinalizeSuccessfulPayment;
use App\Actions\Booking\Payment\RecoverStuckPayment;
use App\Actions\Catalog\CreateScreening;
use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusRetriever;
use App\Enums\Booking\BookingStatus;
use App\Enums\Catalog\Seating\ScreeningSeatStatus;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\RefundAttemptStatus;
use App\Enums\Ticketing\TicketStatus;
use App\Jobs\ProcessStripeWebhook;
use App\Jobs\ReconcilePayment;
use App\Jobs\ReconcileRefund;
use App\Jobs\RetryUnknownRefund;
use App\Models\Booking\Booking;
use App\Models\Catalog\Movie;
use App\Models\Catalog\ScreeningRoom;
use App\Models\Catalog\Seat;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentWebhookEvent;
use App\Models\User;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\ProviderPaymentStatus;
use App\Support\Payment\StripePaymentGateway;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('formats supported currencies from integer minor units', function (): void {
    expect(Money::fromMinorUnits(1250, 'USD')->format())->toBe('12.50 USD')
        ->and(Money::fromMinorUnits(250000, 'VND')->format())->toBe('250,000 VND')
        ->and(fn () => Money::fromMinorUnits(100, 'XXX')->format())->toThrow(InvalidArgumentException::class);
});

it('alerts on stuck, unknown, requires-refund and orphan webhook records', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->with('payments.anomalies', Mockery::on(function (array $context): bool {
            return $context['stuck_processing'] === 1
                && $context['unknown'] === 1
                && $context['requires_refund'] === 1
                && $context['orphan_webhooks'] === 1;
        }));

    foreach ([PaymentStatus::Processing, PaymentStatus::Unknown, PaymentStatus::RequiresRefund] as $status) {
        $booking = Booking::factory()->create();
        $payment = $booking->payment()->create([
            'provider' => 'stripe',
            'status' => $status,
            'amount_minor_units' => $booking->amount_minor_units,
            'currency' => $booking->currency,
            'processing_started_at' => $status === PaymentStatus::Processing ? now()->subHour() : null,
        ]);

        if ($status === PaymentStatus::Unknown) {
            $payment->forceFill(['updated_at' => now()->subHour()])->saveQuietly();
        }
    }
    $webhook = PaymentWebhookEvent::query()->create([
        'provider' => PaymentProvider::Stripe,
        'event_id' => 'evt_alert_orphan',
        'provider_payment_id' => 'pi_alert_orphan',
        'provider_object_type' => 'payment_intent',
        'payload' => ['id' => 'evt_alert_orphan'],
    ]);
    $webhook->forceFill([
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ])->saveQuietly();

    $this->artisan('payments:alert-stuck')
        ->expectsOutput('Found 1 stuck, 1 unknown, 1 requiring refund, 1 orphan webhook(s), 0 unknown refund(s) and 0 failed outbox delivery/message(s).')
        ->assertExitCode(0);

});

it('retries unknown refunds without a provider refund id through the refund action', function (): void {
    Queue::fake();

    $booking = Booking::factory()->create();
    $payment = $booking->payment()->create([
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_refund_retry',
        'status' => PaymentStatus::RequiresRefund,
        'amount_minor_units' => $booking->amount_minor_units,
        'currency' => $booking->currency,
    ]);
    $payment->refundAttempts()->create([
        'attempt_key' => 'refund-retry-without-provider-id',
        'status' => RefundAttemptStatus::Unknown,
        'next_reconcile_at' => now()->subMinute(),
    ]);

    $this->artisan('payments:retry-refunds')->assertSuccessful();

    Queue::assertPushed(RetryUnknownRefund::class, fn (RetryUnknownRefund $job): bool => $job->bookingId === $booking->getKey());
    Queue::assertNotPushed(ReconcileRefund::class);
});

it('reports a pending admin refund instead of claiming it already succeeded', function (): void {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create();
    $payment = $booking->payment()->create([
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_admin_refund_pending',
        'status' => PaymentStatus::Refunding,
        'amount_minor_units' => $booking->amount_minor_units,
        'currency' => $booking->currency,
    ]);

    app()->instance(PaymentGateway::class, new class implements PaymentGateway
    {
        public function charge(Payment $payment): PaymentResult
        {
            return new PaymentResult('failed');
        }

        public function refund(Payment $payment): PaymentResult
        {
            return new PaymentResult('refunding', 're_admin_pending');
        }
    });

    $this->actingAs($admin)
        ->post(route('admin.bookings.refund', $booking))
        ->assertRedirect()
        ->assertSessionHas('warning', 'booking.messages.refund_in_progress');
});

it('does not treat a pending Stripe refund response as finalized', function (): void {
    config()->set('services.stripe.secret', 'sk_test_refund');
    Http::fake([
        'https://api.stripe.com/v1/refunds' => Http::response([
            'id' => 're_pending',
            'status' => 'pending',
            'payment_intent' => 'pi_refund_pending',
        ], 200),
    ]);
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999991,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_refund_pending',
        'status' => PaymentStatus::Succeeded,
        'amount_minor_units' => 100000,
        'currency' => 'VND',
    ]);

    expect(app(StripePaymentGateway::class)->refund($payment)->status)->toBe('refunding');
});

it('finalizes a refund only after a succeeded refund webhook', function (): void {
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute(User::factory()->create(), $screening, [$seat->id], 'refund-webhook-success');
    $payment = $booking->payment()->create([
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_refund_webhook',
        'status' => PaymentStatus::Succeeded,
        'amount_minor_units' => $booking->amount_minor_units,
        'currency' => 'VND',
    ]);
    app(FinalizeSuccessfulPayment::class)->execute($payment);
    PaymentWebhookEvent::query()->create([
        'provider' => 'stripe',
        'event_id' => 'evt_refund_succeeded',
        'provider_payment_id' => 're_webhook_succeeded',
        'provider_object_type' => 'refund',
        'payload' => [
            'id' => 'evt_refund_succeeded',
            'type' => 'refund.updated',
            'data' => ['object' => [
                'id' => 're_webhook_succeeded',
                'status' => 'succeeded',
                'payment_intent' => 'pi_refund_webhook',
                'amount' => 100000,
                'currency' => 'vnd',
            ]],
        ],
    ]);

    (new ProcessStripeWebhook('evt_refund_succeeded'))->handle(app(FinalizeSuccessfulPayment::class));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($screening->screeningSeats()->firstOrFail()->refresh()->status)->toBe(ScreeningSeatStatus::Available);
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

    expect(app(PayBooking::class)->execute($booking)->status)->toBe(PaymentStatus::Processing)
        ->and(app(PayBooking::class)->execute($booking)->status)->toBe(PaymentStatus::Processing)
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
        ->and($booking->items()->firstOrFail()->status)->toBe(TicketStatus::Reserved)
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
        ->and($payment->status)->toBe(PaymentStatus::Processing);
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

it('moves an unavailable reconciliation to manual review after its deadline', function (): void {
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999999,
        'provider' => 'stripe',
        'provider_payment_id' => null,
        'status' => PaymentStatus::Processing,
        'amount_minor_units' => 100000,
        'currency' => 'VND',
        'reconciliation_deadline' => now()->subMinute(),
        'next_reconcile_at' => now()->subMinute(),
    ]);
    $payment->attempts()->create([
        'attempt_key' => 'deadline-reconciliation-attempt',
        'status' => PaymentAttemptStatus::Unknown,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
    ]);
    $retriever = new class implements PaymentStatusRetriever
    {
        public function retrieve(string $providerPaymentId): ProviderPaymentStatus
        {
            return new ProviderPaymentStatus('unknown', $providerPaymentId, failureMessage: 'Stripe search is eventually consistent.');
        }

        public function retrieveByAttemptKey(string $attemptKey): ProviderPaymentStatus
        {
            return new ProviderPaymentStatus('unknown', failureMessage: 'Stripe search is eventually consistent.');
        }
    };

    (new ReconcilePayment($payment->id))->handle($retriever, app(FinalizeSuccessfulPayment::class));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Unknown)
        ->and($payment->failure_message)->toContain('Manual review')
        ->and($payment->next_reconcile_at)->toBeNull()
        ->and($payment->last_reconciliation_error)->toBe('Stripe search is eventually consistent.');
});

it('redirects a confirmed booking to its success page during payment polling', function (): void {
    $user = User::factory()->create();
    $booking = Booking::factory()->for($user)->confirmed()->create();
    $booking->payment()->create([
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_confirmed_poll',
        'status' => PaymentStatus::Succeeded,
        'amount_minor_units' => $booking->amount_minor_units,
        'currency' => $booking->currency,
    ]);

    $this->actingAs($user)
        ->getJson(route('user.bookings.payment-status', $booking))
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::Succeeded->value)
        ->assertJsonPath('redirect', route('user.bookings.success', $booking));
});

it('syncs a completed Stripe payment when the webhook is delayed', function (): void {
    $user = User::factory()->create();
    $room = ScreeningRoom::factory()->create();
    $seat = Seat::factory()->for($room, 'room')->create();
    $screening = app(CreateScreening::class)->execute(Movie::factory()->create(), $room, now()->addDay()->toDateTimeString(), now()->addDay()->addHours(2)->toDateTimeString(), 100000);
    $booking = app(HoldSeats::class)->execute($user, $screening, [$seat->id], 'payment-sync');
    $payment = $booking->payment()->create([
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_sync',
        'status' => PaymentStatus::Processing,
        'amount_minor_units' => $booking->amount_minor_units,
        'currency' => $booking->currency,
        'attempts' => 1,
    ]);
    $payment->attempts()->create([
        'attempt_key' => 'payment-sync-attempt',
        'status' => PaymentAttemptStatus::Processing,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
    ]);

    app()->instance(PaymentStatusRetriever::class, new class($booking->id) implements PaymentStatusRetriever
    {
        public function __construct(private readonly int $bookingId) {}

        public function retrieve(string $providerPaymentId): ProviderPaymentStatus
        {
            return new ProviderPaymentStatus('succeeded', $providerPaymentId, [
                'amount_received' => 100000,
                'currency' => 'vnd',
                'metadata' => [
                    'payable_type' => Booking::class,
                    'payable_id' => (string) $this->bookingId,
                ],
            ]);
        }

        public function retrieveByAttemptKey(string $attemptKey): ProviderPaymentStatus
        {
            return $this->retrieve('pi_sync');
        }
    });

    $this->actingAs($user)
        ->postJson(route('user.bookings.payment-sync', $booking))
        ->assertOk()
        ->assertJsonPath('status', PaymentStatus::Succeeded->value)
        ->assertJsonPath('redirect', route('user.bookings.success', $booking));

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
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
        ->and($payment->refresh()->reconciliation_attempts)->toBe(2);
});

it('does not charge again when the first gateway response times out', function (): void {
    Queue::fake();
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

    Queue::assertPushed(ReconcilePayment::class, fn (ReconcilePayment $job): bool => $job->paymentId === $booking->payment->id);
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

    expect($result->status)->toBe('requires_payment_method')
        ->and($result->metadata['client_secret'])->toBe('pi_payment_element_secret');
    Http::assertSent(function ($request) use ($attemptKey): bool {
        $body = $request->body();

        return ! str_contains($body, 'confirm=true')
            && str_contains($body, 'metadata%5Battempt_key%5D='.$attemptKey)
            && str_contains($body, 'payment_method_types%5B0%5D=card');
    });
});

it('maps a Stripe requires-action intent for the 3DS browser flow', function (): void {
    Http::fake([
        'https://api.stripe.com/v1/payment_intents' => Http::response([
            'id' => 'pi_requires_action',
            'status' => 'requires_action',
            'client_secret' => 'pi_requires_action_secret',
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
        'attempt_key' => 'booking-payment-3ds',
        'status' => PaymentAttemptStatus::Processing,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
    ]);

    $result = app(StripePaymentGateway::class)->charge($payment);

    expect($result->status)->toBe(PaymentStatus::RequiresAction->value)
        ->and($result->metadata['client_secret'])->toBe('pi_requires_action_secret');
});

it('does not finalize a failed Stripe refund response', function (): void {
    Http::fake([
        'https://api.stripe.com/v1/refunds' => Http::response([
            'id' => 're_failed',
            'status' => 'failed',
            'failure_reason' => 'lost_or_stolen_card',
        ], 200),
    ]);
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999999,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_refund_failed',
        'amount_minor_units' => 100000,
        'currency' => 'VND',
        'status' => PaymentStatus::RequiresRefund,
    ]);

    $result = app(StripePaymentGateway::class)->refund($payment);

    expect($result->status)->toBe(PaymentStatus::Failed->value)
        ->and($result->failureMessage)->toBe('lost_or_stolen_card');
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
        ->and($payment->status)->toBe(PaymentStatus::Processing);
});

it('keeps requires payment method as a recoverable reconciliation state', function (): void {
    $payment = Payment::query()->create([
        'payable_type' => Booking::class,
        'payable_id' => 999999,
        'provider' => 'stripe',
        'provider_payment_id' => 'pi_requires_method',
        'status' => PaymentStatus::Processing,
        'amount_minor_units' => 100000,
        'currency' => 'VND',
        'attempts' => 1,
    ]);
    $payment->attempts()->create([
        'attempt_key' => 'requires-method-lookup',
        'status' => PaymentAttemptStatus::Processing,
        'amount_minor_units' => $payment->amount_minor_units,
        'currency' => $payment->currency,
    ]);

    $retriever = new class implements PaymentStatusRetriever
    {
        public function retrieve(string $providerPaymentId): ProviderPaymentStatus
        {
            return new ProviderPaymentStatus('requires_payment_method', $providerPaymentId, [
                'amount' => 100000,
                'currency' => 'vnd',
                'metadata' => [
                    'payable_type' => Booking::class,
                    'payable_id' => '999999',
                ],
            ]);
        }

        public function retrieveByAttemptKey(string $attemptKey): ProviderPaymentStatus
        {
            return $this->retrieve('pi_requires_method');
        }
    };

    (new ReconcilePayment($payment->id))->handle($retriever, app(FinalizeSuccessfulPayment::class));

    expect($payment->refresh()->status)->toBe(PaymentStatus::RequiresPaymentMethod)
        ->and($payment->attempts()->latest('id')->firstOrFail()->status)->toBe(PaymentAttemptStatus::RequiresPaymentMethod);
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

it('ignores unsupported Stripe charge events without creating orphan retries', function (): void {
    Queue::fake();
    $payload = ['id' => 'evt_charge_ignored', 'type' => 'charge.succeeded', 'data' => ['object' => ['id' => 'ch_ignored']]];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertNoContent();

    expect(PaymentWebhookEvent::query()->where('event_id', 'evt_charge_ignored')->firstOrFail())
        ->processed_at->not->toBeNull()
        ->and(PaymentWebhookEvent::query()->where('event_id', 'evt_charge_ignored')->value('orphaned_at'))->toBeNull();
    Queue::assertNotPushed(ProcessStripeWebhook::class);
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
