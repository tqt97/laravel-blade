<?php

namespace App\Contracts;

use App\Support\Payment\ProviderRefundStatus;

interface RefundStatusRetriever
{
    public function retrieveRefund(string $providerRefundId): ProviderRefundStatus;
}
