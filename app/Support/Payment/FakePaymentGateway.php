<?php

namespace App\Support\Payment;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusRetriever;
use App\Contracts\RefundStatusRetriever;
use App\Models\Payment\Payment;

final class FakePaymentGateway implements PaymentGateway, PaymentStatusRetriever, RefundStatusRetriever
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

    public function retrieveByAttemptKey(string $attemptKey): ProviderPaymentStatus
    {
        return new ProviderPaymentStatus('unknown', metadata: ['fake' => true, 'attempt_key' => $attemptKey]);
    }

    public function retrieveRefund(string $providerRefundId): ProviderRefundStatus
    {
        return new ProviderRefundStatus('succeeded', $providerRefundId, metadata: ['fake' => true]);
    }
}
