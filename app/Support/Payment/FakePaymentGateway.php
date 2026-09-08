<?php

namespace App\Support\Payment;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusRetriever;
use App\Models\Payments\Payment;

final class FakePaymentGateway implements PaymentGateway, PaymentStatusRetriever
{
    public function charge(Payment $payment): PaymentResult
    {
        return new PaymentResult('succeeded', 'fake_'.$payment->id, ['fake' => true]);
    }

    public function refund(Payment $payment): PaymentResult
    {
        return new PaymentResult('refunded', $payment->provider_payment_id, ['fake' => true]);
    }

    public function retrieve(string $providerPaymentId): ProviderPaymentStatus
    {
        return new ProviderPaymentStatus('succeeded', $providerPaymentId, ['fake' => true]);
    }
}
