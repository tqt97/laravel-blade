<?php

namespace App\Jobs;

use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Actions\Payment\TransitionPayment;
use App\Contracts\PaymentStatusRetriever;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentAttempt;
use App\Support\Payment\PaymentStateMachine;
use App\Support\Payment\ProviderPaymentStatus;
use App\Support\Time\BookingClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReconcilePayment implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @return array<int, int> */
    public function backoff(): array
    {
        /** @var array<int, int> $backoff */
        $backoff = config('booking.payment.reconciliation_backoff_seconds', []);

        return $backoff;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $paymentId)
    {
        $this->tries = (int) config('booking.payment.reconciliation_tries', 10);
    }

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
        $nextReconcileAt = BookingClock::parseStored($payment->getRawOriginal('next_reconcile_at'));
        if ($nextReconcileAt?->isFuture()) {
            return;
        }
        /** @var PaymentAttempt|null $latestAttempt */
        $latestAttempt = $payment->attempts()->latest('id')->first();
        if (blank($payment->provider_payment_id)) {
            if ($latestAttempt === null) {
                return;
            }

            $providerStatus = $retriever->retrieveByAttemptKey($latestAttempt->attemptKey());
            if (filled($providerStatus->providerPaymentId)) {
                $providerMetadata = $providerStatus->metadata;
                unset($providerMetadata['client_secret']);
                $payment->forceFill([
                    'provider_payment_id' => $providerStatus->providerPaymentId,
                    'provider_metadata' => $providerMetadata,
                    'reconciliation_attempted_at' => null,
                    'next_reconcile_at' => now(),
                    'reconciliation_deadline' => BookingClock::parseStored($payment->getRawOriginal('reconciliation_deadline'))
                        ?? BookingClock::now()->addMinutes((int) config('booking.payment.reconciliation_deadline_minutes', 30)),
                ])->save();
                $payment->refresh();
            }
        } else {
            $providerStatus = $retriever->retrieve((string) $payment->provider_payment_id);
        }
        if ($providerStatus->status === 'unknown') {
            $payment->refresh();
            $deadline = BookingClock::parseStored($payment->getRawOriginal('reconciliation_deadline'))
                ?? BookingClock::now()->addMinutes((int) config('booking.payment.reconciliation_deadline_minutes', 30));
            $error = $providerStatus->failureMessage ?? 'Provider payment status is temporarily unavailable.';
            $payment->forceFill([
                'reconciliation_attempts' => ((int) $payment->reconciliation_attempts) + 1,
                'reconciliation_attempted_at' => now(),
                'reconciliation_deadline' => $deadline,
                'last_reconciliation_error' => $error,
                'next_reconcile_at' => now()->addSeconds($this->retryDelay($deadline)),
            ])->save();

            if (now()->greaterThanOrEqualTo($deadline)) {
                $payment->forceFill([
                    'status' => PaymentStatus::Unknown,
                    'next_reconcile_at' => null,
                    'failure_message' => 'Payment reconciliation deadline exceeded. Manual review is required.',
                ])->save();

                return;
            }

            if ($this->attempts() < $this->tries) {
                $this->release($this->retryDelay($deadline));
            }

            return;
        }
        if (! $this->matchesPayment($payment, $providerStatus)) {
            DB::transaction(function () use ($payment): void {
                $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $locked->forceFill([
                    'status' => PaymentStatus::Unknown,
                    'processing_started_at' => null,
                    'failure_message' => 'Provider payment amount, currency, or booking metadata does not match the local payment.',
                    'last_reconciliation_error' => 'Provider payment amount, currency, or booking metadata does not match the local payment.',
                    'next_reconcile_at' => null,
                ])->save();
                app(TransitionPayment::class)->execute($locked, PaymentAttemptStatus::Unknown, failureMessage: $locked->failure_message, targetStatus: PaymentStatus::Unknown);
            }, 3);

            return;
        }
        $payment = DB::transaction(function () use ($payment, $providerStatus, $stateMachine): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $providerMetadata = $providerStatus->metadata;
            unset($providerMetadata['client_secret']);
            if ($locked->getRawOriginal('status') === PaymentStatus::Succeeded->value) {
                return $locked;
            }
            $status = match ($providerStatus->status) {
                'succeeded' => PaymentStatus::Succeeded,
                'requires_action' => PaymentStatus::RequiresAction,
                'processing' => PaymentStatus::Processing,
                'requires_payment_method' => PaymentStatus::RequiresPaymentMethod,
                'canceled', 'failed' => PaymentStatus::Failed,
                default => PaymentStatus::from((string) $locked->getRawOriginal('status')),
            };
            if (! $stateMachine->canTransition(PaymentStatus::from((string) $locked->getRawOriginal('status')), $status)) {
                return $locked;
            }
            $locked->forceFill([
                'status' => $status,
                'provider_status' => $providerStatus->status,
                'provider_payment_id' => $providerStatus->providerPaymentId,
                'provider_metadata' => $providerMetadata,
                'client_secret' => data_get($providerStatus->metadata, 'client_secret'),
                'next_reconcile_at' => null,
                'reconciliation_deadline' => null,
                'last_reconciliation_error' => null,
                'failure_message' => $providerStatus->failureMessage,
                'processing_started_at' => $status === PaymentStatus::Succeeded || $status === PaymentStatus::Failed ? null : $locked->processing_started_at,
                'paid_at' => $status === PaymentStatus::Succeeded ? now() : $locked->paid_at,
            ])->save();
            app(TransitionPayment::class)->execute(
                $locked,
                PaymentAttemptStatus::fromPaymentStatus($status),
                $providerStatus->providerPaymentId,
                $providerStatus->failureMessage,
                $status,
            );

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

    private function retryDelay(CarbonImmutable $deadline): int
    {
        $now = now();
        $windowStart = $deadline->subMinutes((int) config('booking.payment.reconciliation_deadline_minutes', 30));
        $fastWindowEnd = $windowStart->addMinutes((int) config('booking.payment.reconciliation_fast_window_minutes', 5));

        return $now->lessThan($fastWindowEnd)
            ? (int) config('booking.payment.reconciliation_fast_retry_seconds', 30)
            : ($now->lessThan($deadline)
                ? (int) config('booking.payment.reconciliation_normal_retry_seconds', 300)
                : (int) config('booking.payment.reconciliation_late_retry_seconds', 900));
    }
}
