<?php

namespace App\Actions\Movie\Booking;

use App\Contracts\PaymentGateway;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\RefundAttemptStatus;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\RefundAttempt;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\PaymentStateMachine;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RefundBooking
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentStateMachine $stateMachine,
        private readonly FinalizeRefund $finalizeRefund,
    ) {}

    public function execute(Booking $booking): Payment
    {
        /** @var array{payment: Payment, attempt: ?RefundAttempt, provider_already_refunded: bool} $claim */
        $claim = DB::transaction(function () use ($booking): array {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            $payment = Payment::query()->where('payable_type', Booking::class)->where('payable_id', $booking->getKey())->lockForUpdate()->firstOrFail();

            $paymentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));
            if ($paymentStatus === PaymentStatus::Refunded) {
                return ['payment' => $payment, 'attempt' => null, 'provider_already_refunded' => false];
            }

            if (! $paymentStatus->isRefundable()) {
                throw new BookingOperationFailed(__('booking.messages.refund_successful_only'));
            }

            $existing = RefundAttempt::query()
                ->where('payment_id', $payment->getKey())
                ->open()
                ->latest('id')
                ->first();

            if ($existing?->getRawOriginal('status') === RefundAttemptStatus::Processing->value) {
                return [
                    'payment' => $payment,
                    'attempt' => null,
                    'provider_already_refunded' => false,
                ];
            }
            if ($existing?->getRawOriginal('status') === RefundAttemptStatus::Unknown->value) {
                $existing->forceFill(['status' => RefundAttemptStatus::Processing, 'failure_message' => null, 'started_at' => now()])->save();

                return ['payment' => $payment, 'attempt' => $existing, 'provider_already_refunded' => false];
            }
            $succeededAttempt = $payment->refundAttempts()->where('status', RefundAttemptStatus::Succeeded)->latest('id')->first();
            if ($succeededAttempt !== null) {
                return ['payment' => $payment, 'attempt' => $succeededAttempt, 'provider_already_refunded' => true];
            }

            $items = $booking->items()->lockForUpdate()->get();
            if ($items->contains(fn ($item): bool => $item->getAttribute('status') === TicketStatus::CheckedIn)) {
                throw new BookingOperationFailed(__('booking.messages.checked_in_cannot_refund'));
            }

            $attemptNumber = $payment->refundAttempts()->count() + 1;
            $attempt = $payment->refundAttempts()->create([
                'attempt_key' => config('booking.payment.refund_idempotency_key_prefix', 'booking-refund-').$payment->id.'-'.$attemptNumber,
                'status' => RefundAttemptStatus::Processing,
                'started_at' => now(),
            ]);
            if ($this->stateMachine->canTransition($paymentStatus, PaymentStatus::Refunding)) {
                $payment->setAttribute('status', PaymentStatus::Refunding);
                $payment->save();
            }

            return ['payment' => $payment, 'attempt' => $attempt, 'provider_already_refunded' => false];
        }, 3);
        $payment = $claim['payment'];
        if ($claim['attempt'] === null) {
            return $payment;
        }

        if ($claim['provider_already_refunded']) {
            $result = new PaymentResult('refunded', $claim['attempt']->provider_refund_id, (array) $claim['attempt']->metadata);
        } else {
            try {
                $result = $this->gateway->refund($payment);
            } catch (Throwable $exception) {
                $claim['attempt']->forceFill(['status' => RefundAttemptStatus::Unknown, 'failure_message' => 'Refund provider response was unknown.'])->save();
                report($exception);

                return $payment->refresh();
            }
        }

        if ($result->status !== 'refunded') {
            $claim['attempt']->forceFill(['status' => RefundAttemptStatus::Failed, 'failure_message' => $result->failureMessage, 'metadata' => $result->metadata, 'completed_at' => now()])->save();
            $payment->forceFill([
                'status' => PaymentStatus::RequiresRefund,
                'failure_message' => $result->failureMessage,
            ])->save();
            throw new BookingOperationFailed($result->failureMessage ?? __('booking.messages.refund_failed'));
        }

        if (blank($result->providerPaymentId)) {
            $claim['attempt']->forceFill([
                'status' => RefundAttemptStatus::Unknown,
                'failure_message' => 'Refund succeeded without a provider refund ID. Reconciliation is required.',
                'metadata' => $result->metadata,
            ])->save();

            return $payment->refresh();
        }

        $claim['attempt']->forceFill(['status' => RefundAttemptStatus::Succeeded, 'provider_refund_id' => $result->providerPaymentId, 'metadata' => $result->metadata, 'completed_at' => now()])->save();

        return $this->finalizeRefund->execute($payment, $result->metadata);
    }
}
