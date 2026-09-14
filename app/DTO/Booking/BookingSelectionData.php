<?php

namespace App\DTO\Booking;

final readonly class BookingSelectionData
{
    /**
     * @param  list<int|string>  $seatIds
     * @param  array<int|string, int|string|null>  $quantities
     */
    public function __construct(
        public array $seatIds,
        public string $idempotencyKey,
        public array $quantities = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            seatIds: array_values((array) ($data['seat_ids'] ?? [])),
            idempotencyKey: (string) ($data['idempotency_key'] ?? ''),
            quantities: (array) ($data['quantities'] ?? []),
        );
    }
}
