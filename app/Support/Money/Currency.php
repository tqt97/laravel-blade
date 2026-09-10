<?php

namespace App\Support\Money;

final class Currency
{
    /** @return list<string> */
    public static function codes(): array
    {
        return ['EUR', 'GBP', 'JPY', 'SGD', 'THB', 'USD', 'VND'];
    }

    public static function normalize(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (! in_array($currency, self::codes(), true)) {
            throw new \InvalidArgumentException('Unsupported currency.');
        }

        return $currency;
    }

    public static function fractionDigits(string $currency): int
    {
        return in_array(self::normalize($currency), ['JPY', 'VND'], true) ? 0 : 2;
    }
}
