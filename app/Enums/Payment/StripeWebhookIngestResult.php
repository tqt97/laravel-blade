<?php

namespace App\Enums\Payment;

enum StripeWebhookIngestResult: string
{
    case Ready = 'ready';
    case Orphan = 'orphan';
    case MissingProviderPaymentId = 'missing_provider_payment_id';
    case Rejected = 'rejected';
}
