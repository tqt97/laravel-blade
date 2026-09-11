<?php

namespace App\Support\Payment;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusRetriever;
use App\Models\Payments\Payment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class StripePaymentGateway implements PaymentGateway, PaymentStatusRetriever
{
    public function charge(Payment $payment): PaymentResult
    {
        $metadata = $payment->getAttribute('metadata');
        $metadata = is_array($metadata) ? $metadata : [];
        $attemptKey = $payment->attempts()->latest('id')->value('attempt_key')
            ?: config('booking.payment.attempt_key_prefix', 'booking-payment-').Str::uuid();
        $hasPaymentMethod = filled($metadata['payment_method_id'] ?? null);
        $parameters = [
            'amount' => $payment->amount_minor_units,
            'currency' => strtolower($payment->currency),
            'payment_method_types[0]' => 'card',
            'metadata[payable_id]' => (string) $payment->payable_id,
            'metadata[payable_type]' => (string) $payment->payable_type,
            'metadata[attempt_key]' => $attemptKey,
        ];

        if ($hasPaymentMethod) {
            $parameters['confirm'] = 'true';
            $parameters['payment_method'] = $metadata['payment_method_id'];
        }

        $response = Http::asForm()->withBasicAuth((string) config('services.stripe.secret'), '')
            ->timeout((int) config('services.stripe.timeout_seconds', 10))
            ->withHeaders(['Idempotency-Key' => (string) $attemptKey])
            ->post('https://api.stripe.com/v1/payment_intents', $parameters);

        if ($response->failed()) {
            $this->logProviderError('payment_intent_create', $response);

            return new PaymentResult('failed', failureMessage: (string) ($response->json('error.message') ?? 'Stripe payment failed.'));
        }

        $status = match ($response->json('status')) {
            'succeeded' => 'succeeded',
            'requires_action', 'requires_confirmation', 'requires_payment_method' => 'requires_action',
            'processing' => 'processing',
            default => 'failed',
        };

        return new PaymentResult(
            $status,
            $response->json('id'),
            $response->json(),
        );
    }

    public function refund(Payment $payment): PaymentResult
    {
        $response = Http::asForm()->withBasicAuth((string) config('services.stripe.secret'), '')
            ->timeout((int) config('services.stripe.timeout_seconds', 10))
            ->withHeaders(['Idempotency-Key' => config('booking.payment.refund_idempotency_key_prefix', 'booking-refund-').$payment->id])
            ->post('https://api.stripe.com/v1/refunds', ['payment_intent' => $payment->provider_payment_id]);

        if ($response->successful()) {
            return new PaymentResult('refunded', $response->json('id'), $response->json());
        }

        $this->logProviderError('refund_create', $response);

        return new PaymentResult('failed', failureMessage: (string) ($response->json('error.message') ?? 'Stripe refund failed.'));
    }

    public function retrieve(string $providerPaymentId): ProviderPaymentStatus
    {
        $response = Http::withBasicAuth((string) config('services.stripe.secret'), '')
            ->timeout((int) config('services.stripe.timeout_seconds', 10))
            ->get('https://api.stripe.com/v1/payment_intents/'.urlencode($providerPaymentId));
        if ($response->failed()) {
            $this->logProviderError('payment_intent_retrieve', $response);

            return new ProviderPaymentStatus('unknown', $providerPaymentId, failureMessage: (string) ($response->json('error.message') ?? 'Stripe payment status unavailable.'));
        }

        return new ProviderPaymentStatus($this->mapStatus((string) $response->json('status')), $providerPaymentId, $response->json());
    }

    public function retrieveByAttemptKey(string $attemptKey): ProviderPaymentStatus
    {
        $response = Http::withBasicAuth((string) config('services.stripe.secret'), '')
            ->timeout((int) config('services.stripe.timeout_seconds', 10))
            ->get('https://api.stripe.com/v1/payment_intents/search', [
                'query' => "metadata['attempt_key']:'".addslashes($attemptKey)."'",
                'limit' => 1,
            ]);

        if ($response->failed()) {
            $this->logProviderError('payment_intent_search', $response);

            return new ProviderPaymentStatus('unknown', failureMessage: (string) ($response->json('error.message') ?? 'Stripe payment search unavailable.'));
        }

        $paymentIntent = $response->json('data.0');
        if (! is_array($paymentIntent) || blank($paymentIntent['id'] ?? null)) {
            return new ProviderPaymentStatus('unknown');
        }

        return new ProviderPaymentStatus(
            $this->mapStatus((string) ($paymentIntent['status'] ?? '')),
            (string) $paymentIntent['id'],
            $paymentIntent,
        );
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'succeeded' => 'succeeded',
            'requires_action', 'requires_confirmation', 'requires_payment_method' => 'requires_action',
            'processing' => 'processing',
            'canceled' => 'canceled',
            default => 'failed',
        };
    }

    private function logProviderError(string $operation, Response $response): void
    {
        Log::warning('stripe.payment_provider_error', [
            'operation' => $operation,
            'http_status' => $response->status(),
            'error_type' => $response->json('error.type'),
            'error_code' => $response->json('error.code'),
            'decline_code' => $response->json('error.decline_code'),
            'message' => $response->json('error.message'),
        ]);
    }
}
