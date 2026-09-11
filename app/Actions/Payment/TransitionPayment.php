<?php

namespace App\Actions\Payment;

use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payments\Payment;
use App\Support\Payment\Exceptions\InvalidPaymentTransition;
use App\Support\Payment\PaymentStateMachine;
use Illuminate\Support\Str;

final class TransitionPayment
{
    public function __construct(private readonly PaymentStateMachine $stateMachine) {}

    public function execute(
        Payment $payment,
        PaymentAttemptStatus $attemptStatus,
        ?string $providerPaymentId = null,
        ?string $failureMessage = null,
        ?PaymentStatus $targetStatus = null,
    ): Payment {
        $currentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));

        if ($targetStatus !== null && ! $this->stateMachine->canTransition($currentStatus, $targetStatus)) {
            throw new InvalidPaymentTransition(sprintf('Cannot transition payment from %s to %s.', $currentStatus->value, $targetStatus->value));
        }

        if ($targetStatus !== null) {
            $payment->setAttribute('status', $targetStatus);
        }

        $attempt = $payment->attempts()->latest('id')->first();
        if ($attempt === null) {
            $payment->attempts()->create([
                'attempt_key' => config('booking.payment.attempt_key_prefix', 'booking-payment-').Str::uuid(),
                'status' => $attemptStatus,
                'provider_payment_id' => $providerPaymentId,
                'amount_minor_units' => $payment->getAttribute('amount_minor_units'),
                'currency' => $payment->getAttribute('currency'),
                'failure_message' => $failureMessage,
                'started_at' => now(),
                'completed_at' => $attemptStatus === PaymentAttemptStatus::Processing ? null : now(),
            ]);
            $payment->setAttribute('attempts', ((int) $payment->getAttribute('attempts')) + 1);
        } elseif (PaymentAttemptStatus::tryFrom((string) $attempt->getRawOriginal('status'))?->isOpen() === true) {
            $attempt->forceFill([
                'status' => $attemptStatus,
                'provider_payment_id' => $providerPaymentId ?? $attempt->getAttribute('provider_payment_id'),
                'failure_message' => $failureMessage,
                'completed_at' => $attemptStatus === PaymentAttemptStatus::Processing ? null : now(),
            ])->save();
        }

        $payment->save();

        return $payment->refresh();
    }
}
