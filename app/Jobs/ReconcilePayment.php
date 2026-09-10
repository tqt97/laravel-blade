<?php

namespace App\Jobs;

use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Contracts\PaymentStatusRetriever;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentAttempt;
use App\Support\Payment\PaymentStateMachine;
use App\Support\Payment\ProviderPaymentStatus;
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
        $stateMachine = app(PaymentStateMachine::class);
        $payment = Payment::query()->find($this->paymentId);
        if ($payment === null) {
            return;
        }
        /** @var PaymentAttempt|null $latestAttempt */
        $latestAttempt = $payment->attempts()->latest('id')->first();
        if (blank($payment->provider_payment_id)) {
            if ($latestAttempt === null) {
                return;
            }

            $providerStatus = $retriever->retrieveByAttemptKey($latestAttempt->attemptKey());
            if (blank($providerStatus->providerPaymentId)) {
                return;
            }

            $payment->forceFill([
                'provider_payment_id' => $providerStatus->providerPaymentId,
                'metadata' => $providerStatus->metadata,
                'reconciliation_attempted_at' => null,
            ])->save();
            $payment->refresh();
        } else {
            $providerStatus = $retriever->retrieve((string) $payment->provider_payment_id);
        }
        if ($providerStatus->status !== 'unknown' && ! $this->matchesPayment($payment, $providerStatus)) {
            DB::transaction(function () use ($payment): void {
                $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $locked->forceFill([
                    'status' => PaymentStatus::Unknown,
                    'processing_started_at' => null,
                    'failure_message' => 'Provider payment amount, currency, or booking metadata does not match the local payment.',
                ])->save();
                $locked->syncLatestAttempt(PaymentAttemptStatus::Unknown, failureMessage: $locked->failure_message);
            }, 3);

            return;
        }
        $payment = DB::transaction(function () use ($payment, $providerStatus, $stateMachine): Payment {
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
            if (! $stateMachine->canTransition(PaymentStatus::from((string) $locked->getRawOriginal('status')), $status)) {
                return $locked;
            }
            $locked->forceFill([
                'status' => $status,
                'provider_payment_id' => $providerStatus->providerPaymentId,
                'metadata' => $providerStatus->metadata,
                'failure_message' => $providerStatus->failureMessage,
                'processing_started_at' => $status === PaymentStatus::Succeeded || $status === PaymentStatus::Failed ? null : $locked->processing_started_at,
                'paid_at' => $status === PaymentStatus::Succeeded ? now() : $locked->paid_at,
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

    private function matchesPayment(Payment $payment, ProviderPaymentStatus $providerStatus): bool
    {
        $metadata = $providerStatus->metadata;
        $amount = $providerStatus->status === 'succeeded'
            ? ($metadata['amount_received'] ?? null)
            : ($metadata['amount'] ?? null);
        $providerPayableType = $metadata['metadata']['payable_type'] ?? null;
        $providerPayableId = $metadata['metadata']['payable_id'] ?? null;

        return $providerStatus->providerPaymentId === $payment->provider_payment_id
            && is_numeric($amount)
            && (int) $amount === (int) $payment->amount_minor_units
            && strtoupper((string) ($metadata['currency'] ?? '')) === strtoupper((string) $payment->currency)
            && $providerPayableType === Booking::class
            && (string) $providerPayableId === (string) $payment->payable_id;
    }
}
