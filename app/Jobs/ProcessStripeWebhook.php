<?php

namespace App\Jobs;

use App\Actions\Movie\Booking\FinalizeSuccessfulPayment;
use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\StripeWebhookEventType;
use App\Enums\Payment\StripeWebhookProcessingResult;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
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
                ->where('provider_payment_id', $providerPaymentId)
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
                    'failure_message' => 'Stripe webhook payable booking does not exist.',
                ])->save();

                return ['status' => StripeWebhookProcessingResult::Done];
            }

            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $webhookEventType = StripeWebhookEventType::tryFromPayload($eventType);
            if ($webhookEventType !== null && ! $this->matchesPayment($payment, $object, $webhookEventType->usesReceivedAmount())) {
                $event->forceFill([
                    'failed_at' => now(),
                    'failure_message' => 'Stripe webhook amount or currency does not match the local payment.',
                ])->save();

                return ['status' => StripeWebhookProcessingResult::Done];
            }

            $metadata = $payment->getAttribute('metadata');
            $metadata = is_array($metadata) ? $metadata : [];
            if (isset($payload['created']) && is_numeric($payload['created'])) {
                $metadata['stripe_last_event_created'] = (int) $payload['created'];
            }
            $target = $webhookEventType?->paymentStatus();
            $current = PaymentStatus::from((string) $payment->getRawOriginal('status'));
            $shouldApply = $target !== null && $this->shouldApplyTransition($payment, $target, (int) ($payload['created'] ?? 0));

            if ($shouldApply) {
                $payment->setAttribute('status', $target);
                $payment->setAttribute('metadata', $metadata);
                $payment->setAttribute('provider_payment_id', $providerPaymentId);

                if ($target === PaymentStatus::Succeeded) {
                    $payment->setAttribute('paid_at', now());
                }

                if ($target === PaymentStatus::Failed) {
                    $payment->setAttribute('failure_message', data_get($object, 'last_payment_error.message', 'Payment failed.'));
                    $payment->setAttribute('processing_started_at', null);
                }

                if ($target === PaymentStatus::Pending) {
                    $payment->setAttribute('processing_started_at', $payment->processing_started_at ?? now());
                }

                if ($target === PaymentStatus::RequiresAction) {
                    $payment->setAttribute('metadata', array_merge($metadata, ['client_secret' => data_get($object, 'client_secret')]));
                    $payment->setAttribute('processing_started_at', null);
                }

                $payment->syncLatestAttempt(
                    PaymentAttemptStatus::fromPaymentStatus($target),
                    $providerPaymentId,
                    $payment->failure_message,
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

    private function shouldApplyTransition(Payment $payment, PaymentStatus $target, int $created): bool
    {
        $metadata = $payment->getAttribute('metadata');
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
