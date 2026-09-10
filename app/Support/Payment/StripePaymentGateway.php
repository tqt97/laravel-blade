<?php

namespace App\Support\Payment;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusRetriever;
use App\Models\Payments\Payment;
use Illuminate\Support\Facades\Http;

final class StripePaymentGateway implements PaymentGateway, PaymentStatusRetriever
{
    public function charge(Payment $payment): PaymentResult
    {
        $parameters = [
            'amount' => $payment->amount_minor_units,
            'currency' => strtolower($payment->currency),
            'confirm' => 'true',
            'metadata[payable_id]' => (string) $payment->payable_id,
            'metadata[payable_type]' => (string) $payment->payable_type,
        ];
        $metadata = json_decode((string) $payment->getRawOriginal('metadata'), true);

        if (is_array($metadata) && filled($metadata['payment_method_id'] ?? null)) {
            $parameters['payment_method'] = $metadata['payment_method_id'];
        }

        $attemptKey = $payment->attempts()->latest('id')->value('attempt_key')
            ?: 'booking-payment-'.$payment->id;

        $response = Http::asForm()->withBasicAuth((string) config('services.stripe.secret'), '')
            ->timeout(10)->withHeaders(['Idempotency-Key' => (string) $attemptKey])
            ->post('https://api.stripe.com/v1/payment_intents', $parameters);

        if ($response->failed()) {
            return new PaymentResult('failed', failureMessage: (string) ($response->json('error.message') ?? 'Stripe payment failed.'));
        }

        $status = match ($response->json('status')) {
            'succeeded' => 'succeeded',
            'requires_action', 'requires_confirmation' => 'requires_action',
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
            ->timeout(10)->withHeaders(['Idempotency-Key' => 'booking-refund-'.$payment->id])
            ->post('https://api.stripe.com/v1/refunds', ['payment_intent' => $payment->provider_payment_id]);

        return $response->successful()
            ? new PaymentResult('refunded', $response->json('id'), $response->json())
            : new PaymentResult('failed', failureMessage: (string) ($response->json('error.message') ?? 'Stripe refund failed.'));
    }

    public function retrieve(string $providerPaymentId): ProviderPaymentStatus
    {
        $response = Http::withBasicAuth((string) config('services.stripe.secret'), '')
            ->timeout(10)
            ->get('https://api.stripe.com/v1/payment_intents/'.urlencode($providerPaymentId));
        if ($response->failed()) {
            return new ProviderPaymentStatus('unknown', $providerPaymentId, failureMessage: (string) ($response->json('error.message') ?? 'Stripe payment status unavailable.'));
        }

        $status = match ($response->json('status')) {
            'succeeded' => 'succeeded',
            'requires_action', 'requires_confirmation' => 'requires_action',
            'processing' => 'processing',
            'canceled' => 'canceled',
            default => 'failed',
        };

        return new ProviderPaymentStatus($status, $providerPaymentId, $response->json());
    }
}
