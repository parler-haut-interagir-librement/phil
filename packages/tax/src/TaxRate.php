<?php

declare(strict_types=1);

namespace Phil\Tax;

use Phil\Money\Money;
use Phil\Tax\Exception\InvalidTaxRate;
use Phil\Tax\Exception\UntaxableBase;

/**
 * One tax rate, named by a code and expressed in parts per million.
 *
 * **Parts per million, not a float and not basis points.** A rate such as 9.975 % has three decimals in
 * percent; basis points cannot hold it, and a float cannot hold it exactly. `99_750` ppm can.
 *
 * The code is what a receipt shows and what a credit note reverses (`GST`, `QST`, `VAT`...); which rates
 * apply to a sale is decided by the application.
 */
final readonly class TaxRate
{
    public const int ONE_MILLION = 1_000_000;

    private function __construct(
        public string $code,
        public int $partsPerMillion,
    ) {
    }

    public static function of(string $code, int $partsPerMillion): self
    {
        if (1 !== preg_match('/^[A-Z][A-Z0-9_-]{0,31}$/D', $code)) {
            throw InvalidTaxRate::code($code);
        }

        if ($partsPerMillion < 0 || $partsPerMillion > self::ONE_MILLION) {
            throw InvalidTaxRate::outOfRange($partsPerMillion);
        }

        return new self($code, $partsPerMillion);
    }

    /**
     * A rate from its percentage as a decimal string, the way tax authorities publish it: `"9.975"` is
     * `99_750` ppm. Up to four decimals, the precision of a part per million; never a float.
     */
    public static function ofPercent(string $code, string $percent): self
    {
        if (1 !== preg_match('/^(\d{1,3})(?:\.(\d{1,4}))?$/D', $percent, $matches)) {
            throw InvalidTaxRate::notAPercent($percent);
        }

        $partsPerMillion = (int) ($matches[1].str_pad($matches[2] ?? '', 4, '0'));

        if ($partsPerMillion > self::ONE_MILLION) {
            throw InvalidTaxRate::notAPercent($percent);
        }

        return self::of($code, $partsPerMillion);
    }

    /**
     * The percentage as a decimal string without trailing zeros, as a receipt prints it: `"9.975"`, `"5"`.
     */
    public function percent(): string
    {
        $whole = intdiv($this->partsPerMillion, 10_000);
        $fraction = rtrim(str_pad((string) ($this->partsPerMillion % 10_000), 4, '0', \STR_PAD_LEFT), '0');

        return '' === $fraction ? (string) $whole : $whole.'.'.$fraction;
    }

    /**
     * The tax on a base, rounded half away from zero to the smallest unit, in integer arithmetic only.
     *
     * Rounding away from zero keeps a reversal exact: the tax on `-base` is exactly the negation of the tax
     * on `base`, which is what a credit note relies on.
     */
    public function taxOn(Money $base): Money
    {
        $magnitude = abs($base->minorUnits);
        $largest = intdiv(\PHP_INT_MAX - self::ONE_MILLION, self::ONE_MILLION);

        if ($magnitude > $largest) {
            throw UntaxableBase::tooLarge($largest);
        }

        $tax = intdiv($magnitude * $this->partsPerMillion + intdiv(self::ONE_MILLION, 2), self::ONE_MILLION);

        return Money::of($base->isNegative() ? -$tax : $tax, $base->currency);
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code && $this->partsPerMillion === $other->partsPerMillion;
    }
}
