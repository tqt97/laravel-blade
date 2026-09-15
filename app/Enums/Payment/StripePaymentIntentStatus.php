<?php

namespace App\Enums\Payment;

enum StripePaymentIntentStatus: string
{
    case Succeeded = 'succeeded';
    case RequiresAction = 'requires_action';
    case RequiresConfirmation = 'requires_confirmation';
    case RequiresPaymentMethod = 'requires_payment_method';
    case Processing = 'processing';
    case Canceled = 'canceled';

    public function toApplicationStatus(): string
    {
        return match ($this) {
            self::Succeeded => PaymentStatus::Succeeded->value,
            self::RequiresAction, self::RequiresConfirmation => PaymentStatus::RequiresAction->value,
            self::RequiresPaymentMethod => PaymentStatus::RequiresPaymentMethod->value,
            self::Processing => PaymentStatus::Processing->value,
            self::Canceled => PaymentStatus::Failed->value,
        };
    }
}
