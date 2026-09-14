<?php

namespace App\Enums\Payment;

enum StripeRefundStatus: string
{
    case Pending = 'pending';
    case RequiresAction = 'requires_action';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';

    public function toApplicationStatus(): string
    {
        return match ($this) {
            self::Succeeded => PaymentStatus::Refunded->value,
            self::Pending, self::RequiresAction => PaymentStatus::Refunding->value,
            self::Failed, self::Canceled => PaymentStatus::Failed->value,
        };
    }

    public function isFailure(): bool
    {
        return match ($this) {
            self::Failed, self::Canceled => true,
            default => false,
        };
    }
}
