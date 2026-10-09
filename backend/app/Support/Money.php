<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Enterprise Financial Precision Abstraction.
 *
 * Utilizes PHP BCMath for arbitrary precision calculation, preventing floating-point
 * rounding drift across ledgers, transactions, and balance aggregations.
 */
class Money
{
    public const DEFAULT_SCALE = 4;
    public const DISPLAY_SCALE = 2;

    protected string $amount;
    protected string $currency;

    public function __construct(string|int|float $amount, string $currency = 'MYR')
    {
        $this->amount = self::normalize($amount);
        $this->currency = strtoupper(trim($currency));
    }

    public static function of(string|int|float $amount, string $currency = 'MYR'): self
    {
        return new self($amount, $currency);
    }

    public static function zero(string $currency = 'MYR'): self
    {
        return new self('0.0000', $currency);
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(self|string|int|float $other): self
    {
        $otherAmount = $other instanceof self ? $other->getAmount() : self::normalize($other);
        return new self(bcadd($this->amount, $otherAmount, self::DEFAULT_SCALE), $this->currency);
    }

    public function subtract(self|string|int|float $other): self
    {
        $otherAmount = $other instanceof self ? $other->getAmount() : self::normalize($other);
        return new self(bcsub($this->amount, $otherAmount, self::DEFAULT_SCALE), $this->currency);
    }

    public function multiply(string|int|float $multiplier): self
    {
        return new self(bcmul($this->amount, self::normalize($multiplier), self::DEFAULT_SCALE), $this->currency);
    }

    public function divide(string|int|float $divisor): self
    {
        $normDivisor = self::normalize($divisor);
        if (bccomp($normDivisor, '0', self::DEFAULT_SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero in financial calculation.');
        }

        return new self(bcdiv($this->amount, $normDivisor, self::DEFAULT_SCALE), $this->currency);
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', self::DEFAULT_SCALE) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::DEFAULT_SCALE) < 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', self::DEFAULT_SCALE) === 0;
    }

    public function compareTo(self|string|int|float $other): int
    {
        $otherAmount = $other instanceof self ? $other->getAmount() : self::normalize($other);
        return bccomp($this->amount, $otherAmount, self::DEFAULT_SCALE);
    }

    public function isGreaterThan(self|string|int|float $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isGreaterThanOrEqualTo(self|string|int|float $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function isLessThan(self|string|int|float $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isLessThanOrEqualTo(self|string|int|float $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function toDecimal(): string
    {
        return number_format((float) $this->amount, self::DISPLAY_SCALE, '.', '');
    }

    public function format(): string
    {
        $symbol = match ($this->currency) {
            'USD' => '$',
            'SGD' => 'S$',
            'EUR' => '€',
            'GBP' => '£',
            default => 'RM ',
        };

        return $symbol . number_format((float) $this->amount, self::DISPLAY_SCALE, '.', ',');
    }

    public function __toString(): string
    {
        return $this->amount;
    }

    protected static function normalize(string|int|float $value): string
    {
        if (is_int($value)) {
            return sprintf('%d.0000', $value);
        }

        if (is_float($value)) {
            return number_format($value, self::DEFAULT_SCALE, '.', '');
        }

        $clean = trim((string) $value);
        if (! is_numeric($clean)) {
            throw new InvalidArgumentException("Invalid monetary value: {$value}");
        }

        return number_format((float) $clean, self::DEFAULT_SCALE, '.', '');
    }
}
