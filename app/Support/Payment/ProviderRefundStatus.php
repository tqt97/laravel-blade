<?php

namespace App\Support\Payment;

final readonly class ProviderRefundStatus
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $status,
        public ?string $providerRefundId = null,
        public ?string $providerPaymentId = null,
        public array $metadata = [],
        public ?string $failureMessage = null,
    ) {}
}
