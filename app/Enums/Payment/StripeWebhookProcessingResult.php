<?php

namespace App\Enums\Payment;

enum StripeWebhookProcessingResult: string
{
    case Done = 'done';
    case Orphan = 'orphan';
    case Finalize = 'finalize';
}
