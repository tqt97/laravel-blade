<?php

namespace App\Enums\Payment;

enum PaymentAttemptStatus: string
{
    case Processing = 'processing';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case RequiresAction = 'requires_action';
    case RequiresPaymentMethod = 'requires_payment_method';
    case Unknown = 'unknown';

    public static function fromPaymentStatus(PaymentStatus $status): self
    {
        return match ($status) {
            PaymentStatus::Succeeded => self::Succeeded,
            PaymentStatus::Failed => self::Failed,
            PaymentStatus::Pending, PaymentStatus::Processing => self::Processing,
            PaymentStatus::RequiresAction => self::RequiresAction,
            PaymentStatus::RequiresPaymentMethod => self::RequiresPaymentMethod,
            default => self::Unknown,
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Processing,
            self::Pending,
            self::RequiresAction,
            self::RequiresPaymentMethod,
            self::Unknown => true,
            self::Succeeded,
            self::Failed => false,
        };
    }
}
