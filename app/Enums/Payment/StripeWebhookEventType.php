<?php

namespace App\Enums\Payment;

enum StripeWebhookEventType: string
{
    case Succeeded = 'payment_intent.succeeded';
    case PaymentFailed = 'payment_intent.payment_failed';
    case Processing = 'payment_intent.processing';
    case RequiresAction = 'payment_intent.requires_action';
    case Canceled = 'payment_intent.canceled';
    case RefundCreated = 'refund.created';
    case RefundUpdated = 'refund.updated';
    case RefundFailed = 'refund.failed';

    public function isRefund(): bool
    {
        return match ($this) {
            self::RefundCreated, self::RefundUpdated, self::RefundFailed => true,
            default => false,
        };
    }

    public function paymentStatus(): ?PaymentStatus
    {
        return match ($this) {
            self::Succeeded => PaymentStatus::Succeeded,
            self::PaymentFailed, self::Canceled => PaymentStatus::Failed,
            self::Processing => PaymentStatus::Processing,
            self::RequiresAction => PaymentStatus::RequiresAction,
            self::RefundCreated, self::RefundUpdated, self::RefundFailed => null,
        };
    }

    public function usesReceivedAmount(): bool
    {
        return $this === self::Succeeded;
    }

    /** @return list<self> */
    public static function paymentIntentEvents(): array
    {
        return [
            self::Succeeded,
            self::PaymentFailed,
            self::Processing,
            self::RequiresAction,
            self::Canceled,
        ];
    }

    /** @return list<self> */
    public static function supported(): array
    {
        return array_merge(self::paymentIntentEvents(), self::refundEvents());
    }

    /** @return list<self> */
    public static function refundEvents(): array
    {
        return [self::RefundCreated, self::RefundUpdated, self::RefundFailed];
    }

    public static function tryFromPayload(mixed $eventType): ?self
    {
        return is_string($eventType) ? self::tryFrom($eventType) : null;
    }
}
