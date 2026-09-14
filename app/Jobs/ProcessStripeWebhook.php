<?php

namespace App\Jobs;

use App\Actions\Movie\Booking\FinalizeRefund;
use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Actions\Payment\TransitionPayment;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\RefundAttemptStatus;
use App\Enums\Payment\StripeRefundStatus;
use App\Enums\Payment\StripeWebhookEventType;
use App\Enums\Payment\StripeWebhookProcessingResult;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
use App\Models\Payments\RefundAttempt;
use App\Support\Payment\PaymentStateMachine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessStripeWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @return array<int, int> */
    public function backoff(): array
    {
        /** @var array<int, int> $backoff */
        $backoff = config('booking.payment.webhook_backoff_seconds', []);

        return $backoff;
    }

    public function __construct(public readonly string $eventId)
    {
        $this->tries = (int) config('booking.payment.webhook_tries', 10);
    }

    public function handle(FinalizeSuccessfulPayment $finalizeSuccessfulPayment): void
    {
        $event = PaymentWebhookEvent::query()->forProvider(PaymentProvider::Stripe)->where('event_id', $this->eventId)->first();
        $payload = $event?->getAttribute('payload');
        $eventType = is_array($payload) ? StripeWebhookEventType::tryFromPayload($payload['type'] ?? null) : null;
        if ($eventType?->isRefund() === true) {
            $this->handleRefundWebhook($eventType, $event, app(FinalizeRefund::class));

            return;
        }

        /** @var array{status: StripeWebhookProcessingResult, payment_id?: int} $result */
        $result = DB::transaction(function (): array {
            $event = PaymentWebhookEvent::query()->lockForUpdate()
                ->forProvider(PaymentProvider::Stripe)
                ->where('event_id', $this->eventId)
                ->first();

            if ($event === null || $event->processed_at !== null || $event->failed_at !== null) {
                return ['status' => StripeWebhookProcessingResult::Done];
            }

            $event->forceFill([
                'processing_attempts' => ((int) $event->processing_attempts) + 1,
                'last_attempt_at' => now(),
            ])->save();

            $providerPaymentId = (string) $event->provider_payment_id;

            $payment = Payment::query()
                ->forProvider(PaymentProvider::Stripe)
                ->where(function ($query) use ($providerPaymentId): void {
                    $query->where('provider_payment_id', $providerPaymentId)
                        ->orWhereHas('attempts', fn ($attempts) => $attempts->where('provider_payment_id', $providerPaymentId));
                })
                ->first();

            if ($payment === null) {
                $event->forceFill(['orphaned_at' => now()])->save();

                return ['status' => StripeWebhookProcessingResult::Orphan];
            }

            /** @var array<string, mixed> $payload */
            $payload = $event->getAttribute('payload');
            $object = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : [];
            $eventType = (string) ($payload['type'] ?? '');

            if ($payment->getAttribute('payable_type') !== Booking::class) {
                $event->forceFill(['processed_at' => now(), 'orphaned_at' => null])->save();

                return ['status' => StripeWebhookProcessingResult::Done];
            }

            $booking = Booking::query()->whereKey($payment->getAttribute('payable_id'))->lockForUpdate()->first();
            if ($booking === null) {
                $event->forceFill([
                    'failed_at' => now(),
                    'failure_message' => __('booking.messages.payment_webhook_booking_missing'),
                ])->save();

                return ['status' => StripeWebhookProcessingResult::Done];
            }

            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $webhookEventType = StripeWebhookEventType::tryFromPayload($eventType);
            if ($webhookEventType !== null && ! $this->matchesPayment($payment, $object, $webhookEventType->usesReceivedAmount())) {
                $event->forceFill([
                    'failed_at' => now(),
                    'failure_message' => __('booking.messages.payment_webhook_mismatch'),
                ])->save();

                return ['status' => StripeWebhookProcessingResult::Done];
            }

            $providerMetadata = $payment->getAttribute('provider_metadata');
            $providerMetadata = is_array($providerMetadata) ? $providerMetadata : [];
            if (isset($payload['created']) && is_numeric($payload['created'])) {
                $providerMetadata['stripe_last_event_created'] = (int) $payload['created'];
            }
            $target = $webhookEventType?->paymentStatus();
            $current = PaymentStatus::from((string) $payment->getRawOriginal('status'));
            $shouldApply = $target !== null && $this->shouldApplyTransition($payment, $target, (int) ($payload['created'] ?? 0));

            if ($shouldApply) {
                $payment->setAttribute('status', $target);
                $payment->setAttribute('provider_status', (string) ($object['status'] ?? $target->value));
                $payment->setAttribute('provider_metadata', $providerMetadata);
                $payment->setAttribute('provider_payment_id', $providerPaymentId);

                if ($target === PaymentStatus::Succeeded) {
                    $payment->setAttribute('paid_at', now());
                }

                if ($target === PaymentStatus::Failed) {
                    $payment->setAttribute('failure_message', data_get($object, 'last_payment_error.message', __('booking.messages.payment_failed')));
                    $payment->setAttribute('processing_started_at', null);
                }

                if ($target->isProcessingState()) {
                    $payment->setAttribute('processing_started_at', $payment->processing_started_at ?? now());
                }

                if ($target->requiresClientAction()) {
                    $payment->setAttribute('client_secret', data_get($object, 'client_secret'));
                    $payment->setAttribute('processing_started_at', null);
                }

                app(TransitionPayment::class)->execute(
                    $payment,
                    PaymentAttemptStatus::fromPaymentStatus($target),
                    $providerPaymentId,
                    $payment->failure_message,
                    $target,
                );

                $payment->save();
            }

            if ($target !== PaymentStatus::Succeeded || ($current !== PaymentStatus::Succeeded && $shouldApply === false)) {
                $event->forceFill([
                    'processed_at' => now(),
                    'orphaned_at' => null,
                ])->save();

                return ['status' => StripeWebhookProcessingResult::Done];
            }

            return ['status' => StripeWebhookProcessingResult::Finalize, 'payment_id' => $payment->getKey()];
        }, 3);

        if ($result['status'] === StripeWebhookProcessingResult::Orphan) {
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff()[min($this->attempts(), count($this->backoff()) - 1)]);
            } else {
                PaymentWebhookEvent::query()->forProvider(PaymentProvider::Stripe)
                    ->where('event_id', $this->eventId)
                    ->update(['failed_at' => now(), 'failure_message' => __('booking.messages.payment_webhook_orphan_retry_exhausted')]);
            }

            return;
        }

        if ($result['status'] !== StripeWebhookProcessingResult::Finalize) {
            return;
        }

        $payment = Payment::query()->findOrFail($result['payment_id']);
        $finalizeSuccessfulPayment->execute($payment);

        PaymentWebhookEvent::query()
            ->forProvider(PaymentProvider::Stripe)
            ->where('event_id', $this->eventId)
            ->update(['processed_at' => now(), 'orphaned_at' => null]);
    }

    private function handleRefundWebhook(StripeWebhookEventType $eventType, ?PaymentWebhookEvent $event, FinalizeRefund $finalizeRefund): void
    {
        if ($event === null || $event->processed_at !== null || $event->failed_at !== null) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = $event->getAttribute('payload');
        $object = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : [];
        $refundId = (string) ($object['id'] ?? '');
        $paymentIntentId = (string) ($object['payment_intent'] ?? '');
        $status = StripeRefundStatus::tryFrom((string) ($object['status'] ?? ($eventType === StripeWebhookEventType::RefundFailed ? StripeRefundStatus::Failed->value : '')));

        $paymentId = DB::transaction(function () use ($event, $object, $refundId, $paymentIntentId, $status): ?int {
            $lockedEvent = PaymentWebhookEvent::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->forProvider(PaymentProvider::Stripe)
                ->where(function ($query) use ($paymentIntentId): void {
                    $query->where('provider_payment_id', $paymentIntentId)
                        ->orWhereHas('attempts', fn ($attempts) => $attempts->where('provider_payment_id', $paymentIntentId));
                })
                ->lockForUpdate()
                ->first();
            if ($payment === null || blank($refundId)) {
                $lockedEvent->forceFill(['failed_at' => now(), 'failure_message' => __('booking.messages.refund_webhook_unlinked')])->save();

                return null;
            }

            if ($payment->getRawOriginal('status') === PaymentStatus::Refunded->value) {
                $lockedEvent->forceFill(['processed_at' => now(), 'orphaned_at' => null])->save();

                return null;
            }

            $attempt = $payment->refundAttempts()->where('provider_refund_id', $refundId)->lockForUpdate()->first()
                ?? RefundAttempt::query()->where('payment_id', $payment->getKey())
                    ->whereIn('status', RefundAttemptStatus::openStatuses())
                    ->latest('id')->lockForUpdate()->first();
            if ($attempt === null) {
                $attempt = $payment->refundAttempts()->create([
                    'attempt_key' => config('booking.payment.refund_webhook_attempt_key_prefix', 'stripe-webhook-refund-').$refundId,
                    'status' => RefundAttemptStatus::Processing,
                    'started_at' => now(),
                ]);
            }

            $attemptStatus = match ($status) {
                StripeRefundStatus::Succeeded => RefundAttemptStatus::Succeeded,
                StripeRefundStatus::Pending, StripeRefundStatus::RequiresAction => RefundAttemptStatus::Pending,
                default => RefundAttemptStatus::Failed,
            };
            $attempt->forceFill([
                'status' => $attemptStatus,
                'provider_refund_id' => $refundId,
                'metadata' => $object,
                'failure_message' => $attemptStatus === RefundAttemptStatus::Failed ? (string) ($object['failure_reason'] ?? 'Stripe refund failed.') : null,
                'completed_at' => $attemptStatus === RefundAttemptStatus::Pending ? null : now(),
                'next_reconcile_at' => $attemptStatus === RefundAttemptStatus::Pending ? now()->addMinutes((int) config('booking.payment.refund_reconciliation_retry_minutes', 5)) : null,
            ])->save();

            if ($attemptStatus === RefundAttemptStatus::Failed) {
                $payment->forceFill(['status' => PaymentStatus::RequiresRefund, 'failure_message' => $attempt->getAttribute('failure_message')])->save();
            } elseif ($attemptStatus === RefundAttemptStatus::Pending) {
                $payment->forceFill(['status' => PaymentStatus::Refunding])->save();
            }
            $lockedEvent->forceFill(['processed_at' => now(), 'orphaned_at' => null])->save();

            return $attemptStatus === RefundAttemptStatus::Succeeded ? $payment->getKey() : null;
        }, 3);

        if ($paymentId !== null) {
            $finalizeRefund->execute(Payment::query()->findOrFail($paymentId), $object);
        }
    }

    private function shouldApplyTransition(Payment $payment, PaymentStatus $target, int $created): bool
    {
        $metadata = $payment->getAttribute('provider_metadata');
        $lastCreated = is_array($metadata) ? (int) ($metadata['stripe_last_event_created'] ?? 0) : 0;
        if ($created > 0 && $lastCreated > $created) {
            return false;
        }

        $current = PaymentStatus::from((string) $payment->getRawOriginal('status'));
        if ($current->isRefundProtected()) {
            return false;
        }

        return app(PaymentStateMachine::class)->canTransition($current, $target)
            || ($current === PaymentStatus::Succeeded && $target === PaymentStatus::Succeeded);
    }

    /** @param array<string, mixed> $object */
    private function matchesPayment(Payment $payment, array $object, bool $successful): bool
    {
        $amount = $successful ? ($object['amount_received'] ?? null) : ($object['amount'] ?? null);
        $currency = strtoupper((string) ($object['currency'] ?? ''));
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

        return is_numeric($amount)
            && (int) $amount === (int) $payment->amount_minor_units
            && $currency === strtoupper((string) $payment->currency)
            && ((string) ($metadata['payable_id'] ?? '') === (string) $payment->payable_id)
            && ((string) ($metadata['payable_type'] ?? '') === Booking::class);
    }
}
