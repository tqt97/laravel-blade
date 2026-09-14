<?php

namespace App\Jobs;

use App\Actions\Booking\Payment\FinalizeRefund;
use App\Contracts\RefundStatusRetriever;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\RefundAttemptStatus;
use App\Enums\Payment\StripeRefundStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\RefundAttempt;
use App\Support\Booking\BookingClock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReconcileRefund implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $refundAttemptId) {}

    public function handle(RefundStatusRetriever $retriever, FinalizeRefund $finalizeRefund): void
    {
        $attempt = RefundAttempt::query()->find($this->refundAttemptId);
        $attemptStatus = $attempt === null ? null : RefundAttemptStatus::tryFrom((string) $attempt->getRawOriginal('status'));
        if ($attempt === null || $attemptStatus === null || ! $attemptStatus->isOpen()) {
            return;
        }
        $nextReconcileAt = BookingClock::parseStored($attempt->getRawOriginal('next_reconcile_at'));
        if ($nextReconcileAt?->isFuture()) {
            return;
        }
        if (blank($attempt->provider_refund_id)) {
            return;
        }

        $provider = $retriever->retrieveRefund((string) $attempt->provider_refund_id);
        $paymentId = DB::transaction(function () use ($attempt, $provider): ?int {
            $locked = RefundAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();
            $locked->increment('reconciliation_attempts');
            $locked->refresh();
            $payment = Payment::query()->whereKey($locked->getAttribute('payment_id'))->lockForUpdate()->firstOrFail();

            if ($provider->providerRefundId !== $locked->getAttribute('provider_refund_id') || $provider->providerPaymentId !== null && $provider->providerPaymentId !== $payment->getAttribute('provider_payment_id')) {
                $locked->forceFill(['status' => RefundAttemptStatus::Unknown, 'failure_message' => __('booking.messages.refund_provider_identity_mismatch'), 'next_reconcile_at' => now()->addMinutes((int) config('booking.payment.refund_reconciliation_retry_minutes', 5))])->save();

                return null;
            }

            $providerStatus = StripeRefundStatus::tryFrom($provider->status);
            $status = match ($providerStatus) {
                StripeRefundStatus::Succeeded => RefundAttemptStatus::Succeeded,
                StripeRefundStatus::Pending, StripeRefundStatus::RequiresAction => RefundAttemptStatus::Pending,
                StripeRefundStatus::Failed, StripeRefundStatus::Canceled => RefundAttemptStatus::Failed,
                default => RefundAttemptStatus::Unknown,
            };
            $maxAttempts = (int) config('booking.payment.refund_reconciliation_max_attempts', 20);
            $manualReview = $status === RefundAttemptStatus::Unknown && $locked->reconciliation_attempts >= $maxAttempts;
            $locked->forceFill([
                'status' => $status,
                'metadata' => $provider->metadata,
                'failure_message' => $provider->failureMessage,
                'completed_at' => $status->isCompleted() ? now() : null,
                'next_reconcile_at' => ! $manualReview && ! $status->isCompleted() ? now()->addMinutes((int) config('booking.payment.refund_reconciliation_retry_minutes', 5)) : null,
            ])->save();

            if ($manualReview) {
                $payment->forceFill(['status' => PaymentStatus::RequiresRefund, 'failure_message' => __('booking.messages.refund_reconciliation_expired')])->save();
            }

            if ($status === RefundAttemptStatus::Failed) {
                $payment->forceFill(['status' => PaymentStatus::RequiresRefund, 'failure_message' => $provider->failureMessage ?? __('booking.messages.refund_failed')])->save();
            } elseif ($status === RefundAttemptStatus::Pending) {
                $payment->forceFill(['status' => PaymentStatus::Refunding])->save();
            }

            return $status === RefundAttemptStatus::Succeeded ? $payment->getKey() : null;
        }, 3);

        if ($paymentId !== null) {
            $finalizeRefund->execute(Payment::query()->findOrFail($paymentId), $provider->metadata);
        }
    }
}
