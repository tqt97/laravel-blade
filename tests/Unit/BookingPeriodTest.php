<?php

use App\Booking\ValueObjects\BookingPeriod;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

it('uses strict half-open overlap semantics', function (): void {
    $period = BookingPeriod::fromDateTimes(
        CarbonImmutable::parse('2026-10-01 10:00 UTC'),
        CarbonImmutable::parse('2026-10-01 11:00 UTC'),
    );

    expect($period->overlaps(BookingPeriod::fromDateTimes(
        CarbonImmutable::parse('2026-10-01 11:00 UTC'),
        CarbonImmutable::parse('2026-10-01 12:00 UTC'),
    )))->toBeFalse();
});

it('rejects an inverted period', function (): void {
    expect(fn () => new BookingPeriod(
        CarbonImmutable::parse('2026-10-01 11:00 UTC'),
        CarbonImmutable::parse('2026-10-01 10:00 UTC'),
    ))->toThrow(InvalidArgumentException::class);
});
