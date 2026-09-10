<?php

declare(strict_types=1);

namespace App\Support\Time;

use Carbon\CarbonImmutable;

final class BookingClock
{
    public static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function parseStored(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, self::timezone());
    }
}
