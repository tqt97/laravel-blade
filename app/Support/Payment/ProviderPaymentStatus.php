<?php

namespace App\Support\Payment;

final readonly class ProviderPaymentStatus
{
    /** @param array<string, mixed> $metadata */
    public function __construct(public string $status, public ?string $providerPaymentId = null, public array $metadata = [], public ?string $failureMessage = null) {}
}
