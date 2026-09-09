<?php

namespace App\Jobs;

use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Contracts\PaymentStatusRetriever;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payments\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReconcilePayment implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $paymentId) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentStatusRetriever $retriever, FinalizeSuccessfulPayment $finalize): void
    {
        $payment = Payment::query()->find($this->paymentId);
        if ($payment === null || blank($payment->provider_payment_id)) {
            return;
        }
        $providerStatus = $retriever->retrieve((string) $payment->provider_payment_id);
        $payment = DB::transaction(function () use ($payment, $providerStatus): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->getRawOriginal('status') === PaymentStatus::Succeeded->value) {
                return $locked;
            }
            $status = match ($providerStatus->status) {
                'succeeded' => PaymentStatus::Succeeded,
                'requires_action' => PaymentStatus::RequiresAction,
                'processing' => PaymentStatus::Pending,
                'canceled', 'failed' => PaymentStatus::Failed,
                default => PaymentStatus::from((string) $locked->getRawOriginal('status')),
            };
            $locked->forceFill([
                'status' => $status,
                'provider_payment_id' => $providerStatus->providerPaymentId,
                'metadata' => $providerStatus->metadata,
                'failure_message' => $providerStatus->failureMessage,
                'processing_started_at' => $status === PaymentStatus::Succeeded || $status === PaymentStatus::Failed ? null : $locked->processing_started_at,
                'paid_at' => $status === PaymentStatus::Succeeded ? now()->utc() : $locked->paid_at,
            ])->save();
            $locked->syncLatestAttempt(match ($status) {
                PaymentStatus::Succeeded => PaymentAttemptStatus::Succeeded,
                PaymentStatus::RequiresAction => PaymentAttemptStatus::RequiresAction,
                PaymentStatus::Pending => PaymentAttemptStatus::Processing,
                PaymentStatus::Failed => PaymentAttemptStatus::Failed,
                default => PaymentAttemptStatus::Unknown,
            }, $providerStatus->providerPaymentId, $providerStatus->failureMessage);

            return $locked->refresh();
        }, 3);
        if ($payment->getRawOriginal('status') === PaymentStatus::Succeeded->value) {
            $finalize->execute($payment);
        }
    }
}
