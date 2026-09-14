<?php

namespace App\DTO\Booking;

final readonly class PayBookingData
{
    /** @param array<int|string, int|string|null> $quantities */
    public function __construct(
        public ?string $paymentMethodId,
        public array $quantities,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paymentMethodId: isset($data['payment_method_id']) ? (string) $data['payment_method_id'] : null,
            quantities: (array) ($data['quantities'] ?? []),
        );
    }
}
