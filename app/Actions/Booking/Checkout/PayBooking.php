<?php

namespace App\Actions\Booking\Checkout;

use App\Actions\Booking\Lifecycle\TransitionBooking;
use App\Actions\Booking\Payment\FinalizeSuccessfulPayment;
use App\Actions\Booking\Payment\MarkPaymentProviderUnknown;
use App\Actions\Commerce\Concessions\SyncBookingConcessions;
use App\Actions\Payment\TransitionPayment;
use App\Contracts\PaymentGateway;
use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\Booking\BookingExpired;
use App\Exceptions\Booking\BookingOperationFailed;
use App\Jobs\ReconcilePayment;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAttempt;
use App\Support\Booking\BookingClock;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\PaymentStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class PayBooking
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly FinalizeSuccessfulPayment $finalizeSuccessfulPayment,
        private readonly MarkPaymentProviderUnknown $markPaymentProviderUnknown,
        private readonly SyncBookingConcessions $syncBookingConcessions,
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
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

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
                $booking = $this->syncBookingConcessions->executeForLockedBooking($booking, $quantitiesByConcession);

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
            // The provider may have accepted the intent before the client
            // observed a timeout. Only mark the exact claimed attempt and
            // never overwrite a concurrent succeeded/refunded payment.
            $payment = $this->markPaymentProviderUnknown->execute($payment, $claim['attempt']?->getKey());

            ReconcilePayment::dispatch($payment->getKey())->afterCommit();

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
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $providerMetadata = $result->metadata;
            unset($providerMetadata['client_secret']);

            $attempt = $attemptId === null
                ? null
                : PaymentAttempt::query()->whereKey($attemptId)->lockForUpdate()->first();

            $status = PaymentStatus::tryFrom($result->status) ?? PaymentStatus::Failed;

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
                $providerIds = $payment->getAttribute('metadata');
                $providerIds = is_array($providerIds) ? $providerIds : [];
                $providerIds['provider_payment_ids'] = array_values(array_unique(array_merge(
                    is_array($providerIds['provider_payment_ids'] ?? null) ? $providerIds['provider_payment_ids'] : [],
                    [$result->providerPaymentId],
                )));
                $payment->setAttribute('provider_payment_id', $result->providerPaymentId);
                $payment->setAttribute('metadata', $providerIds);
            }

            $payment->setAttribute('failure_message', $status === PaymentStatus::Unknown
                ? __('booking.messages.payment_provider_missing_id')
                : $result->failureMessage);

            $payment->setAttribute('processing_started_at', null);

            app(TransitionPayment::class)->execute(
                $payment,
                PaymentAttemptStatus::fromPaymentStatus($status),
                $result->providerPaymentId,
                $result->failureMessage,
                $status,
            );

            if ($status === PaymentStatus::Succeeded) {
                $payment->setAttribute('paid_at', BookingClock::now());
            }

            if ($attempt !== null && $attempt->getRawOriginal('status') === PaymentAttemptStatus::Processing->value) {
                $attempt->forceFill([
                    'status' => PaymentAttemptStatus::fromPaymentStatus($status),
                    'provider_payment_id' => $result->providerPaymentId,
                    'response_metadata' => $providerMetadata,
                    'failure_message' => $payment->failure_message,
                    'completed_at' => $status->isProcessingState() ? null : BookingClock::now(),
                ])->save();
            }
            $payment->save();

            return $payment->refresh();
        }, 3);
    }
}
