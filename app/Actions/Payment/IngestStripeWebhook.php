<?php

namespace App\Actions\Payment;

use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\StripeWebhookEventType;
use App\Enums\Payment\StripeWebhookIngestResult;
use App\Models\Movie\Booking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentWebhookEvent;
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

            if ($event->processed_at !== null) {
                return StripeWebhookIngestResult::Ready;
            }

            if ($event->failed_at !== null) {
                return StripeWebhookIngestResult::Rejected;
            }

            $object = $data['data']['object'] ?? [];
            $providerPaymentId = is_array($object) ? ($object['id'] ?? null) : null;

            if (! is_string($providerPaymentId) || $providerPaymentId === '') {
                $event->forceFill([
                    'failed_at' => now(),
                    'failure_message' => 'Stripe webhook is missing a provider payment ID.',
                ])->save();

                return StripeWebhookIngestResult::MissingProviderPaymentId;
            }

            $event->forceFill([
                'provider_payment_id' => $providerPaymentId,
                'orphaned_at' => Payment::query()->forProvider(PaymentProvider::Stripe)
                    ->where('provider_payment_id', $providerPaymentId)
                    ->exists() ? null : now(),
            ])->save();

            $payment = Payment::query()->forProvider(PaymentProvider::Stripe)
                ->where('provider_payment_id', $providerPaymentId)
                ->first();

            $eventType = StripeWebhookEventType::tryFromPayload($data['type'] ?? null);

            if (
                $payment !== null
                && $eventType !== null
                && ! $this->matchesPayment($payment, $object, $eventType->usesReceivedAmount())
            ) {
                $event->forceFill([
                    'failed_at' => now(),
                    'orphaned_at' => null,
                    'failure_message' => 'Stripe webhook amount, currency, or metadata does not match the local payment.',
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
