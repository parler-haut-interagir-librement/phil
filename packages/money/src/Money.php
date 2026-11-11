<?php

declare(strict_types=1);

namespace Phil\Money;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Exception\InvalidAmount;

/**
 * An amount in the smallest unit of its currency — cents for a dollar — and never a float.
 *
 * **The currency is part of the value.** Two amounts in different currencies cannot be added, subtracted or
 * compared: the operation throws rather than silently mixing them. A negative amount is legitimate (a credit
 * note reverses an invoice line); an amount outside the native integer is not, and is refused instead of being
 * turned into a float by PHP. The range is symmetric — `PHP_INT_MIN` is refused — so that every amount can be
 * negated.
 *
 * **Decimals are strings.** `ofDecimal('12.34', $cad)` reads what a person or a file wrote, to the precision of
 * the currency and no finer; `toDecimal()` writes it back the same way.
 */
final readonly class Money
{
    private function __construct(
        public int $minorUnits,
        public Currency $currency,
    ) {
    }

    public static function of(int $minorUnits, Currency $currency): self
    {
        return new self(self::checked($minorUnits), $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Reads a decimal amount such as `12.34`, `-0.5` or `1200`: digits, an optional leading `-` and an optional
     * `.` followed by digits. Trailing zeros are not precision — `12.50` is fine for the yen as `12.5` is not — but
     * a decimal finer than the currency counts is refused rather than rounded.
     */
    public static function ofDecimal(string $amount, Currency $currency): self
    {
        if (1 !== preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $amount, $parts)) {
            throw InvalidAmount::notADecimal($amount);
        }

        $fractionDigits = $currency->fractionDigits();
        $fraction = rtrim($parts[3] ?? '', '0');

        if (\strlen($fraction) > $fractionDigits) {
            throw InvalidAmount::tooPrecise($amount, $currency->code, $fractionDigits);
        }

        $digits = ltrim($parts[2].str_pad($fraction, $fractionDigits, '0'), '0');
        $maximum = (string) \PHP_INT_MAX;

        if (\strlen($digits) > \strlen($maximum) || (\strlen($digits) === \strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw InvalidAmount::outOfRange();
        }

        $magnitude = (int) $digits;

        return new self('-' === $parts[1] ? -$magnitude : $magnitude, $currency);
    }

    /**
     * The sum of one or more amounts, all in the same currency.
     */
    public static function sum(self $first, self ...$others): self
    {
        return array_reduce($others, static fn (self $total, self $amount): self => $total->add($amount), $first);
    }

    /**
     * The amount as a decimal, to the precision of its currency: `12.34`, `-0.05`, `1200` for the yen.
     */
    public function toDecimal(): string
    {
        $fractionDigits = $this->currency->fractionDigits();
        $sign = $this->minorUnits < 0 ? '-' : '';

        if (0 === $fractionDigits) {
            return (string) $this->minorUnits;
        }

        $digits = str_pad((string) abs($this->minorUnits), $fractionDigits + 1, '0', \STR_PAD_LEFT);

        return $sign.substr($digits, 0, -$fractionDigits).'.'.substr($digits, -$fractionDigits);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(self::checked($this->minorUnits + $other->minorUnits), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(self::checked($this->minorUnits - $other->minorUnits), $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minorUnits, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->minorUnits), $this->currency);
    }

    /**
     * The amount times a whole number: a quantity, a count of months. A fraction of an amount is `allocate()`'s
     * job, which never loses a unit to rounding.
     */
    public function multiply(int $factor): self
    {
        return new self(self::checked($this->minorUnits * $factor), $this->currency);
    }

    /**
     * Splits the amount by ratios, without losing or creating a unit: the parts always add up to the amount.
     *
     * Each part receives its share rounded down; the units left over go one by one to the parts whose share was
     * rounded down the most, the earliest part first on a tie. `allocate(1, 1, 1)` of 1.00 gives 0.34, 0.33 and
     * 0.33; a part of ratio 0 receives nothing. A negative amount is split as its magnitude would be, negated.
     *
     * @return list<self>
     */
    public function allocate(int ...$ratios): array
    {
        if ([] === $ratios) {
            throw InvalidAmount::because('An amount is allocated by one ratio or more.');
        }

        $total = 0;

        foreach ($ratios as $ratio) {
            if ($ratio < 0) {
                throw InvalidAmount::because(\sprintf('An allocation ratio is zero or more; %d is not.', $ratio));
            }

            $total = self::checked($total + $ratio);
        }

        if (0 === $total) {
            throw InvalidAmount::because('The allocation ratios add up to zero.');
        }

        $shares = [];
        $remainders = [];

        foreach ($ratios as $index => $ratio) {
            $product = self::checked($this->minorUnits * $ratio);
            $shares[$index] = intdiv($product, $total);
            $remainders[$index] = abs($product % $total);
        }

        $left = $this->minorUnits - array_sum($shares);
        // Largest remainder first; `arsort` keeps the earlier part first among equal remainders.
        arsort($remainders);

        foreach (\array_slice(array_keys($remainders), 0, abs($left)) as $index) {
            $shares[$index] += $left <=> 0;
        }

        $amounts = [];

        foreach ($shares as $share) {
            $amounts[] = new self($share, $this->currency);
        }

        return $amounts;
    }

    /**
     * Splits the amount into equal parts, the earliest parts receiving the units left over.
     *
     * @return list<self>
     */
    public function split(int $parts): array
    {
        if ($parts < 1) {
            throw InvalidAmount::because(\sprintf('An amount is split into one part or more; %d is not.', $parts));
        }

        $share = intdiv($this->minorUnits, $parts);
        $left = $this->minorUnits % $parts;
        $amounts = [];

        for ($index = 0; $index < $parts; ++$index) {
            $amounts[] = new self($index < abs($left) ? $share + ($left <=> 0) : $share, $this->currency);
        }

        return $amounts;
    }

    public function isZero(): bool
    {
        return 0 === $this->minorUnits;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency) && $this->minorUnits === $other->minorUnits;
    }

    /**
     * -1, 0 or 1, as `<=>`; two amounts in different currencies are not comparable.
     */
    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minorUnits <=> $other->minorUnits;
    }

    public function isGreaterThan(self $other): bool
    {
        return 1 === $this->compare($other);
    }

    public function isLessThan(self $other): bool
    {
        return -1 === $this->compare($other);
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }

    /**
     * Integer arithmetic that leaves the native range yields a float in PHP; an amount never does, and never
     * reaches `PHP_INT_MIN`, which has no positive counterpart.
     */
    private static function checked(float|int $result): int
    {
        if (!\is_int($result) || \PHP_INT_MIN === $result) {
            throw InvalidAmount::outOfRange();
        }

        return $result;
    }
}
