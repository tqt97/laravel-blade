<?php

namespace App\Enums\Payment;

enum RefundAttemptStatus: string
{
    case Processing = 'processing';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function isOpen(): bool
    {
        return match ($this) {
            self::Processing, self::Pending, self::Unknown => true,
            default => false,
        };
    }

    public function isCompleted(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed => true,
            default => false,
        };
    }

    /** @return list<self> */
    public static function openStatuses(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }
}
