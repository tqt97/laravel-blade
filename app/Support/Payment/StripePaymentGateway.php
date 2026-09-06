<?php

namespace App\Support\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Payments\Payment;
use Illuminate\Support\Facades\Http;

final class StripePaymentGateway implements PaymentGateway
{
    public function charge(Payment $payment): PaymentResult
    {
        $parameters = ['amount' => $payment->amount_minor_units, 'currency' => strtolower($payment->currency), 'confirm' => 'true', 'metadata[payable_id]' => (string) $payment->payable_id, 'metadata[payable_type]' => (string) $payment->payable_type];
        $metadata = json_decode((string) $payment->getRawOriginal('metadata'), true);
        if (is_array($metadata) && filled($metadata['payment_method_id'] ?? null)) {
            $parameters['payment_method'] = $metadata['payment_method_id'];
        }
        $response = Http::asForm()->withBasicAuth((string) config('services.stripe.secret'), '')
            ->timeout(10)->withHeaders(['Idempotency-Key' => 'booking-payment-'.$payment->id])
            ->post('https://api.stripe.com/v1/payment_intents', $parameters);

        if ($response->failed()) {
            return new PaymentResult('failed', failureMessage: (string) ($response->json('error.message') ?? 'Stripe payment failed.'));
        }

        return new PaymentResult(
            $response->json('status') === 'succeeded' ? 'succeeded' : 'pending',
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
            ? new PaymentResult('refunded', $payment->provider_payment_id, $response->json())
            : new PaymentResult('failed', failureMessage: (string) ($response->json('error.message') ?? 'Stripe refund failed.'));
    }
}
