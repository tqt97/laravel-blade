<?php

namespace App\Contracts;

use App\Support\Payment\ProviderPaymentStatus;

interface PaymentStatusRetriever
{
    public function retrieve(string $providerPaymentId): ProviderPaymentStatus;
}
