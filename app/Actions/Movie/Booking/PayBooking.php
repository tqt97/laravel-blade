<?php

namespace App\Actions\Movie\Booking;

use App\Actions\Movie\Concessions\AddConcessions;
use App\Actions\Payment\TransitionPayment;
use App\Contracts\PaymentGateway;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentAttempt;
use App\Support\Booking\Exceptions\BookingExpired;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\PaymentStateMachine;
use App\Support\Time\BookingClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class PayBooking
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly FinalizeSuccessfulPayment $finalizeSuccessfulPayment,
        private readonly AddConcessions $addConcessions,
        private readonly PaymentStateMachine $stateMachine,
    ) {}

    /** @param array<int, int> $quantitiesByConcession */
    public function execute(Booking $booking, ?string $paymentMethodId = null, array $quantitiesByConcession = []): Payment
    {
        /** @var array{payment: Payment, should_charge: bool, attempt: ?PaymentAttempt} $claim */
        $claim = DB::transaction(function () use ($booking, $paymentMethodId, $quantitiesByConcession): array {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            if ($status === BookingStatus::Confirmed) {
                $payment = Payment::query()->where([
                    'payable_type' => Booking::class,
                    'payable_id' => $booking->id,
                ])->first();

                if ($payment === null) {
                    throw new BookingOperationFailed(__('booking.messages.payment_not_found'));
                }

                return ['payment' => $payment, 'should_charge' => false, 'attempt' => null];
            }

            if (! $status->isPayable()) {
                throw new BookingOperationFailed(__('booking.messages.booking_cannot_be_paid'));
            }

            if ($booking->expires_at !== null && BookingClock::parseStored($booking->getRawOriginal('expires_at'))?->lessThanOrEqualTo(BookingClock::now())) {
                throw new BookingExpired(__('booking.messages.booking_expired'));
            }

            $payment = Payment::query()->firstOrCreate([
                'payable_type' => Booking::class,
                'payable_id' => $booking->id,
            ], [
                'provider' => PaymentProvider::configured()->value,
                'status' => PaymentStatus::Pending,
                'amount_minor_units' => $booking->amount_minor_units,
                'currency' => $booking->currency,
            ]);

            $paymentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));
            if (
                $paymentStatus === PaymentStatus::Succeeded
                || $paymentStatus === PaymentStatus::RequiresAction
                || $paymentStatus === PaymentStatus::Processing
                || $paymentStatus === PaymentStatus::Unknown
                || ($paymentStatus === PaymentStatus::Pending && filled($payment->provider_payment_id))
            ) {
                return [
                    'payment' => $payment,
                    'should_charge' => false,
                    'attempt' => null,
                ];
            }

            if ($status === BookingStatus::Held && $quantitiesByConcession !== []) {
                $booking = $this->addConcessions->executeForLockedBooking($booking, $quantitiesByConcession);

                $payment->forceFill([
                    'amount_minor_units' => $booking->amount_minor_units,
                    'currency' => $booking->currency,
                ])->save();
            }

            $nextAttempt = ((int) $payment->attempts) + 1;
            $attempt = $payment->attempts()->create([
                // A provider idempotency key must identify one immutable
                // charge attempt. It must not depend on a counter that can
                // drift after a retry or a partially persisted transition.
                'attempt_key' => config('booking.payment.attempt_key_prefix', 'booking-payment-').Str::uuid(),
                'status' => PaymentAttemptStatus::Processing,
                'amount_minor_units' => $payment->amount_minor_units,
                'currency' => $payment->currency,
                'payment_method_reference' => $paymentMethodId,
                'request_metadata' => ['payment_method_supplied' => $paymentMethodId !== null],
                'started_at' => BookingClock::now(),
            ]);

            $payment->forceFill([
                'status' => PaymentStatus::Processing,
                'attempts' => $nextAttempt,
                'processing_started_at' => BookingClock::now(),
                'last_attempt_at' => BookingClock::now(),
            ])->save();

            if ($status === BookingStatus::Held) {
                app(TransitionBooking::class)->execute($booking, BookingStatus::PendingPayment);
            }

            return [
                'payment' => $payment->refresh(),
                'should_charge' => true,
                'attempt' => $attempt,
            ];
        }, 3);
        $payment = $claim['payment'];
        if (! $claim['should_charge']) {
            return $payment;
        }

        try {
            $result = $this->gateway->charge($payment);
        } catch (Throwable $exception) {
            $claim['attempt']?->forceFill([
                'status' => PaymentAttemptStatus::Unknown,
                'failure_message' => 'Payment provider response was unknown.',
            ])->save();

            app(TransitionPayment::class)->execute(
                $payment,
                PaymentAttemptStatus::Unknown,
                failureMessage: 'Payment provider response was unknown.',
            );

            $payment->forceFill([
                // Keep the claim processing so a retry cannot create a
                // second provider intent before reconciliation identifies
                // the first one by its attempt key.
                'status' => PaymentStatus::Processing,
                'failure_message' => 'Payment provider response was unknown. Reconciliation is required.',
            ])->save();

            report($exception);

            return $payment->refresh();
        }
        $payment = $this->applyResult($payment, $result, $claim['attempt']?->getKey());

        return $payment->getRawOriginal('status') === PaymentStatus::Succeeded->value
            ? $this->finalizeSuccessfulPayment->execute($payment)
            : $payment;
    }

    private function applyResult(Payment $payment, PaymentResult $result, ?int $attemptId): Payment
    {
        return DB::transaction(function () use ($payment, $result, $attemptId): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $providerMetadata = $result->metadata;
            unset($providerMetadata['client_secret']);

            $attempt = $attemptId === null
                ? null
                : PaymentAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();

            $status = match ($result->status) {
                'succeeded' => PaymentStatus::Succeeded,
                'requires_action' => PaymentStatus::RequiresAction,
                'pending', 'processing' => PaymentStatus::Processing,
                'requires_payment_method' => PaymentStatus::RequiresPaymentMethod,
                default => PaymentStatus::Failed,
            };

            if ($status === PaymentStatus::Succeeded && blank($result->providerPaymentId)) {
                $status = PaymentStatus::Unknown;
            }

            $currentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));
            if (! $this->stateMachine->canTransition($currentStatus, $status)) {
                return $payment;
            }

            $payment->setAttribute('status', $status);
            $payment->setAttribute('provider_status', $result->status);
            $payment->setAttribute('provider_metadata', $providerMetadata);
            $payment->setAttribute('client_secret', data_get($result->metadata, 'client_secret'));
            if (filled($result->providerPaymentId)) {
                $payment->setAttribute('provider_payment_id', $result->providerPaymentId);
            }
            $payment->setAttribute('failure_message', $status === PaymentStatus::Unknown
                ? 'Payment succeeded without a provider payment ID. Reconciliation is required.'
                : $result->failureMessage);
            $payment->setAttribute('processing_started_at', null);
            app(TransitionPayment::class)->execute(
                $payment,
                match ($status) {
                    PaymentStatus::Succeeded => PaymentAttemptStatus::Succeeded,
                    PaymentStatus::RequiresAction => PaymentAttemptStatus::RequiresAction,
                    PaymentStatus::RequiresPaymentMethod => PaymentAttemptStatus::RequiresPaymentMethod,
                    PaymentStatus::Processing => PaymentAttemptStatus::Processing,
                    PaymentStatus::Unknown => PaymentAttemptStatus::Unknown,
                    default => PaymentAttemptStatus::Failed,
                },
                $result->providerPaymentId,
                $result->failureMessage,
                $status,
            );

            if ($status === PaymentStatus::Succeeded) {
                $payment->setAttribute('paid_at', now());
            }

            if ($attempt !== null && $attempt->getRawOriginal('status') === PaymentAttemptStatus::Processing->value) {
                $attempt->forceFill([
                    'status' => match ($status) {
                        PaymentStatus::Succeeded => PaymentAttemptStatus::Succeeded,
                        PaymentStatus::RequiresAction => PaymentAttemptStatus::RequiresAction,
                        PaymentStatus::RequiresPaymentMethod => PaymentAttemptStatus::RequiresPaymentMethod,
                        PaymentStatus::Processing => PaymentAttemptStatus::Processing,
                        PaymentStatus::Unknown => PaymentAttemptStatus::Unknown,
                        default => PaymentAttemptStatus::Failed,
                    },
                    'provider_payment_id' => $result->providerPaymentId,
                    'response_metadata' => $providerMetadata,
                    'failure_message' => $payment->failure_message,
                    'completed_at' => in_array($status, [PaymentStatus::Pending, PaymentStatus::Processing], true) ? null : now(),
                ])->save();
            }
            $payment->save();

            return $payment->refresh();
        }, 3);
    }
}
