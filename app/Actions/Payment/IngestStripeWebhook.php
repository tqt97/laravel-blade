<?php

namespace App\Actions\Payment;

use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\StripeWebhookEventType;
use App\Enums\Payment\StripeWebhookIngestResult;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentWebhookEvent;
use App\Support\Booking\BookingClock;
use Illuminate\Support\Facades\DB;

final class IngestStripeWebhook
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, string $eventId): StripeWebhookIngestResult
    {
        return DB::transaction(function () use ($data, $eventId): StripeWebhookIngestResult {
            $event = PaymentWebhookEvent::query()->firstOrCreate([
                'provider' => PaymentProvider::Stripe->value,
                'event_id' => $eventId,
            ], ['payload' => $data]);

            if (! $event->wasRecentlyCreated && hash('sha256', json_encode($event->payload, JSON_THROW_ON_ERROR)) !== hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))) {
                $event->forceFill([
                    'failed_at' => BookingClock::now(),
                    'failure_message' => __('booking.messages.payment_webhook_duplicate_payload'),
                ])->save();

                return StripeWebhookIngestResult::Rejected;
            }

            if ($event->processed_at !== null) {
                return StripeWebhookIngestResult::Ready;
            }

            if ($event->failed_at !== null) {
                return StripeWebhookIngestResult::Rejected;
            }

            $eventType = StripeWebhookEventType::tryFromPayload($data['type'] ?? null);
            if ($eventType === null) {
                $event->forceFill([
                    'processed_at' => BookingClock::now(),
                    'failure_message' => __('booking.messages.payment_webhook_unsupported'),
                ])->save();

                return StripeWebhookIngestResult::Ignored;
            }

            $object = $data['data']['object'] ?? [];
            $providerPaymentId = is_array($object) ? ($object['id'] ?? null) : null;

            if (! is_string($providerPaymentId) || $providerPaymentId === '') {
                $event->forceFill([
                    'failed_at' => BookingClock::now(),
                    'failure_message' => __('booking.messages.payment_webhook_missing_id'),
                ])->save();

                return StripeWebhookIngestResult::MissingProviderPaymentId;
            }

            $isRefund = $eventType->isRefund();
            $paymentIntentId = $object['payment_intent'] ?? null;
            if ($isRefund && (! is_string($paymentIntentId) || $paymentIntentId === '')) {
                $event->forceFill([
                    'provider_payment_id' => $providerPaymentId,
                    'provider_object_type' => 'refund',
                    'orphaned_at' => BookingClock::now(),
                    'failure_message' => __('booking.messages.refund_webhook_missing_payment_intent'),
                ])->save();

                return StripeWebhookIngestResult::Orphan;
            }
            $linkedPaymentId = $isRefund ? $paymentIntentId : $providerPaymentId;
            $event->forceFill([
                'provider_payment_id' => $providerPaymentId,
                'provider_object_type' => $isRefund ? 'refund' : 'payment_intent',
                'orphaned_at' => Payment::query()->forProvider(PaymentProvider::Stripe)
                    ->where(function ($query) use ($linkedPaymentId): void {
                        $query->where('provider_payment_id', $linkedPaymentId)
                            ->orWhereHas('attempts', fn ($attempts) => $attempts->where('provider_payment_id', $linkedPaymentId));
                    })
                    ->exists() ? null : BookingClock::now(),
            ])->save();

            $payment = Payment::query()->forProvider(PaymentProvider::Stripe)
                ->where(function ($query) use ($linkedPaymentId): void {
                    $query->where('provider_payment_id', $linkedPaymentId)
                        ->orWhereHas('attempts', fn ($attempts) => $attempts->where('provider_payment_id', $linkedPaymentId));
                })
                ->first();

            if (
                $payment !== null
                && ! $isRefund
                && ! $this->matchesPayment($payment, $object, $eventType->usesReceivedAmount())
            ) {
                $event->forceFill([
                    'failed_at' => BookingClock::now(),
                    'orphaned_at' => null,
                    'failure_message' => __('booking.messages.payment_webhook_mismatch'),
                ])->save();

                return StripeWebhookIngestResult::Rejected;
            }

            return $payment === null
                ? StripeWebhookIngestResult::Orphan
                : StripeWebhookIngestResult::Ready;
        }, 3);
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
