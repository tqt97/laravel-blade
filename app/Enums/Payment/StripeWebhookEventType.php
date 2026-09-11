<?php

namespace App\Enums\Payment;

enum StripeWebhookEventType: string
{
    case Succeeded = 'payment_intent.succeeded';
    case PaymentFailed = 'payment_intent.payment_failed';
    case Processing = 'payment_intent.processing';
    case RequiresAction = 'payment_intent.requires_action';
    case Canceled = 'payment_intent.canceled';

    public function paymentStatus(): PaymentStatus
    {
        return match ($this) {
            self::Succeeded => PaymentStatus::Succeeded,
            self::PaymentFailed, self::Canceled => PaymentStatus::Failed,
            self::Processing => PaymentStatus::Pending,
            self::RequiresAction => PaymentStatus::RequiresAction,
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

    public static function tryFromPayload(mixed $eventType): ?self
    {
        return is_string($eventType) ? self::tryFrom($eventType) : null;
    }
}
