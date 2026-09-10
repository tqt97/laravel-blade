<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;

final readonly class Money
{
    public readonly int $minorUnits;

    public readonly string $currency;

    public function __construct(int $minorUnits, string $currency)
    {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        $this->minorUnits = $minorUnits;
        $this->currency = Currency::normalize($currency);
    }

    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Money quantity cannot be negative.');
        }

        return new self($this->minorUnits * $quantity, $this->currency);
    }

    public function format(): string
    {
        $fractionDigits = Currency::fractionDigits($this->currency);
        $thousandsSeparator = app()->getLocale() === 'vi' ? '.' : ',';
        $decimalSeparator = app()->getLocale() === 'vi' ? ',' : '.';

        if ($fractionDigits === 0) {
            $amount = number_format($this->minorUnits, 0, $decimalSeparator, $thousandsSeparator);
        } else {
            $wholeUnits = intdiv($this->minorUnits, 100);
            $fractionalUnits = str_pad((string) ($this->minorUnits % 100), 2, '0', STR_PAD_LEFT);
            $amount = number_format($wholeUnits, 0, $decimalSeparator, $thousandsSeparator).$decimalSeparator.$fractionalUnits;
        }

        return $amount.' '.$this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money values must use the same currency.');
        }
    }
}
