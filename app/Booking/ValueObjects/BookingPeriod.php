<?php

namespace App\Booking\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class BookingPeriod
{
    public function __construct(public CarbonImmutable $startAt, public CarbonImmutable $endAt)
    {
        if ($this->startAt->greaterThanOrEqualTo($this->endAt)) {
            throw new InvalidArgumentException('A booking period must start before it ends.');
        }
    }

    public static function fromDateTimes(CarbonImmutable $startAt, CarbonImmutable $endAt): self
    {
        return new self($startAt->utc(), $endAt->utc());
    }

    public function overlaps(self $other): bool
    {
        return $this->startAt->lessThan($other->endAt)
            && $this->endAt->greaterThan($other->startAt);
    }
}
