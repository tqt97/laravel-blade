<?php

namespace App\Actions\Movie\Booking;

use App\Actions\Movie\Concessions\AddConcessions;
use App\Contracts\PaymentGateway;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentAttempt;
use App\Support\Booking\Exceptions\BookingExpired;
use App\Support\Payment\PaymentResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class PayBooking
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly FinalizeSuccessfulPayment $finalizeSuccessfulPayment,
        private readonly AddConcessions $addConcessions,
    ) {}

    /** @param array<int, int> $quantitiesByConcession */
    public function execute(Booking $booking, ?string $paymentMethodId = null, array $quantitiesByConcession = []): Payment
    {
        /** @var array{payment: Payment, should_charge: bool, attempt: ?PaymentAttempt} $claim */
        $claim = DB::transaction(function () use ($booking, $quantitiesByConcession): array {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            if ($status === BookingStatus::Confirmed) {
                return ['payment' => Payment::query()->firstOrCreate([
                    'payable_type' => Booking::class,
                    'payable_id' => $booking->id,
                ], [
                    'provider' => config('booking.payment.provider', 'fake'),
                    'status' => PaymentStatus::Succeeded,
                    'amount_minor_units' => $booking->amount_minor_units,
                    'currency' => $booking->currency,
                    'paid_at' => now()->utc(),
                ]), 'should_charge' => false, 'attempt' => null];
            }
            if (! in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
                throw new RuntimeException(__('booking.messages.booking_cannot_be_paid'));
            }
            if ($booking->expires_at !== null && CarbonImmutable::parse($booking->getRawOriginal('expires_at'), 'UTC')->lessThanOrEqualTo(now()->utc())) {
                throw new BookingExpired(__('booking.messages.booking_expired'));
            }
            $payment = Payment::query()->firstOrCreate([
                'payable_type' => Booking::class,
                'payable_id' => $booking->id,
            ], [
                'provider' => config('booking.payment.provider', 'fake'),
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
                'attempt_key' => 'booking-payment-'.$payment->id.'-'.$nextAttempt,
                'status' => PaymentAttemptStatus::Processing,
                'amount_minor_units' => $payment->amount_minor_units,
                'currency' => $payment->currency,
                'started_at' => now()->utc(),
            ]);
            $payment->forceFill([
                'status' => PaymentStatus::Processing,
                'attempts' => $nextAttempt,
                'processing_started_at' => now()->utc(),
                'last_attempt_at' => now()->utc(),
            ])->save();
            if ($status === BookingStatus::Held) {
                $booking->transitionTo(BookingStatus::PendingPayment);
                $booking->save();
            }

            return ['payment' => $payment->refresh(), 'should_charge' => true, 'attempt' => $attempt];
        }, 3);
        $payment = $claim['payment'];
        if (! $claim['should_charge']) {
            return $payment;
        }

        if ($paymentMethodId !== null) {
            $metadata = $payment->getAttribute('metadata');
            $payment->setAttribute('metadata', array_merge(is_array($metadata) ? $metadata : [], ['payment_method_id' => $paymentMethodId]));
            $payment->save();
        }

        try {
            $result = $this->gateway->charge($payment);
        } catch (Throwable $exception) {
            $claim['attempt']?->forceFill([
                'status' => PaymentAttemptStatus::Unknown,
                'failure_message' => 'Payment provider response was unknown.',
            ])->save();

            $payment->syncLatestAttempt(PaymentAttemptStatus::Unknown, null, 'Payment provider response was unknown.');

            $payment->forceFill([
                'status' => PaymentStatus::Unknown,
                'failure_message' => 'Payment provider response was unknown. Manual reconciliation is required.',
                'processing_started_at' => null,
            ])->save();

            report($exception);

            return $payment->refresh();
        }
        $this->completeAttempt($claim['attempt'], $result);
        $payment = $this->applyResult($payment, $result);

        return $payment->getRawOriginal('status') === PaymentStatus::Succeeded->value
            ? $this->finalizeSuccessfulPayment->execute($payment)
            : $payment;
    }

    private function completeAttempt(?PaymentAttempt $attempt, PaymentResult $result): void
    {
        $status = match ($result->status) {
            'succeeded' => PaymentAttemptStatus::Succeeded,
            'requires_action' => PaymentAttemptStatus::RequiresAction,
            'pending', 'processing' => PaymentAttemptStatus::Processing,
            default => PaymentAttemptStatus::Failed,
        };

        $attempt?->forceFill([
            'status' => $status,
            'provider_payment_id' => $result->providerPaymentId,
            'metadata' => $result->metadata,
            'failure_message' => $result->failureMessage,
            'completed_at' => $status === PaymentAttemptStatus::Processing ? null : now()->utc(),
        ])->save();
    }

    private function applyResult(Payment $payment, PaymentResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (PaymentStatus::from((string) $payment->getRawOriginal('status')) === PaymentStatus::Succeeded) {
                return $payment;
            }

            $status = match ($result->status) {
                'succeeded' => PaymentStatus::Succeeded,
                'requires_action' => PaymentStatus::RequiresAction,
                'pending', 'processing' => PaymentStatus::Pending,
                default => PaymentStatus::Failed,
            };

            $payment->setAttribute('status', $status);
            $payment->setAttribute('provider_payment_id', $result->providerPaymentId);
            $payment->setAttribute('metadata', $result->metadata);
            $payment->setAttribute('failure_message', $result->failureMessage);
            $payment->setAttribute('processing_started_at', null);
            $payment->syncLatestAttempt(
                match ($status) {
                    PaymentStatus::Succeeded => PaymentAttemptStatus::Succeeded,
                    PaymentStatus::RequiresAction => PaymentAttemptStatus::RequiresAction,
                    PaymentStatus::Pending => PaymentAttemptStatus::Processing,
                    default => PaymentAttemptStatus::Failed,
                },
                $result->providerPaymentId,
                $result->failureMessage,
            );

            if ($status === PaymentStatus::Succeeded) {
                $payment->setAttribute('paid_at', now()->utc());
            }
            $payment->save();

            return $payment->refresh();
        }, 3);
    }
}
