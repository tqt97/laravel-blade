<?php

namespace App\Enums\Payment;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case RequiresAction = 'requires_action';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case RequiresRefund = 'requires_refund';
    case Refunding = 'refunding';
    case Unknown = 'unknown';

    /** @return list<self> */
    public static function reconciliationCandidates(): array
    {
        return [self::Processing, self::Pending, self::RequiresAction, self::Unknown];
    }

    public function isRefundProtected(): bool
    {
        return in_array($this, [self::Refunded, self::RequiresRefund], true);
    }

    public function isAwaitingProviderResolution(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::RequiresAction, self::Unknown], true);
    }

    public function isRefundable(): bool
    {
        return in_array($this, [self::Succeeded, self::RequiresRefund, self::Refunding], true);
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($this) {
            self::Pending => in_array($target, [self::Processing, self::RequiresAction, self::Succeeded, self::Failed, self::Unknown], true),
            self::Processing => in_array($target, [self::Pending, self::RequiresAction, self::Succeeded, self::Failed, self::Unknown], true),
            self::RequiresAction => in_array($target, [self::Processing, self::Pending, self::Succeeded, self::Failed, self::Unknown], true),
            self::Failed => in_array($target, [self::Processing, self::Pending, self::RequiresAction, self::Succeeded, self::Unknown], true),
            self::Unknown => in_array($target, [self::Pending, self::Processing, self::RequiresAction, self::Succeeded, self::Failed, self::RequiresRefund], true),
            self::Succeeded => in_array($target, [self::RequiresRefund, self::Refunding, self::Refunded], true),
            self::RequiresRefund => in_array($target, [self::Refunding, self::Refunded], true),
            self::Refunding => in_array($target, [self::RequiresRefund, self::Refunded], true),
            self::Refunded => false,
        };
    }

    /** @return list<string> */
    public static function browserTerminalValues(): array
    {
        return [self::Failed->value, self::Refunded->value, self::RequiresRefund->value];
    }
}
