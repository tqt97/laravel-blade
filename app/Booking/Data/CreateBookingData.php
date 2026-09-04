<?php

namespace App\Booking\Data;

use App\Booking\ValueObjects\BookingPeriod;

final readonly class CreateBookingData
{
    public function __construct(
        public int $resourceId,
        public BookingPeriod $period,
        public ?string $idempotencyKey = null,
    ) {}
}
