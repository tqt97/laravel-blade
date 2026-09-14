<?php

namespace App\DTO\Catalog;

final readonly class CreateScreeningData
{
    public function __construct(
        public int $movieId,
        public int $screeningRoomId,
        public string $startsAt,
        public string $endsAt,
        public int $basePriceMinorUnits,
        public string $currency,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            movieId: (int) $data['movie_id'],
            screeningRoomId: (int) $data['screening_room_id'],
            startsAt: (string) $data['starts_at'],
            endsAt: (string) $data['ends_at'],
            basePriceMinorUnits: (int) $data['base_price_minor_units'],
            currency: strtoupper((string) $data['currency']),
        );
    }
}
