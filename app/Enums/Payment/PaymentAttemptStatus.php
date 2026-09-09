<?php

namespace App\Enums\Payment;

enum PaymentAttemptStatus: string
{
    case Processing = 'processing';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case RequiresAction = 'requires_action';
    case Unknown = 'unknown';
}
