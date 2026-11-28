<?php

declare(strict_types=1);

namespace Phil\Measurement;

use Phil\Measurement\Exception\IncompatibleUnits;
use Phil\Measurement\Exception\InvalidMeasurement;
use RoundingMode;
use Stringable;

/**
 * An exact value in a unit: `12.5 kg`, `-40 Cel`, `31.4 [mi_i]/[gal_us]`. Never a float.
 *
 * **The value is exact.** It is read from a decimal string and kept as a fraction, so that a chain of conversions —
 * pounds to kilograms to ounces — loses nothing in between; it is rounded once, when it is written with
 * `toDecimal()` or `round()`.
 *
 * **The unit is part of the value.** Only measurements of the same family convert, compare and add; the rest throws
 * `IncompatibleUnits`. Two temperatures are not added: a temperature takes a temperature difference, and a
 * temperature minus a temperature is a difference. Measurements of a family with a reciprocal unit, such as fuel
 * consumption, convert and compare but are not added.
 */
final readonly class Measurement implements Stringable
{
    private function __construct(
        private Fraction $value,
        public Unit $unit,
    ) {
    }

    /**
     * A measurement from a decimal such as `12.5`, `-0.25` or `1200`, of 18 significant digits at most.
     */
    public static function of(string $value, Unit $unit): self
    {
        $exact = Fraction::parse($value);

        if ($unit->reciprocal && 0 === $exact->sign()) {
            throw InvalidMeasurement::divisionByZero();
        }

        return new self($exact, $unit);
    }

    /**
     * @internal
     */
    public static function exact(Fraction $value, Unit $unit): self
    {
        return new self($value, $unit);
    }

    /**
     * The same quantity in another unit of the family, exactly.
     */
    public function convertTo(Unit $unit): self
    {
        if ($unit->code === $this->unit->code) {
            return $this;
        }

        $this->assertSameFamily($unit);

        return new self($unit->fromStandard($this->unit->toStandard($this->value)), $unit);
    }

    /**
     * The sum, in the unit of this measurement. A measurement of an affine family, a temperature, takes a measurement
     * of its family of differences.
     */
    public function add(self $other): self
    {
        $difference = $this->unit->difference;

        if ($difference instanceof Unit && $other->unit->family === $difference->family) {
            return new self($this->value->add($other->convertTo($difference)->value), $this->unit);
        }

        $this->assertSameFamily($other->unit);
        $this->assertAdditive();

        return new self($this->value->add($other->convertTo($this->unit)->value), $this->unit);
    }

    /**
     * The difference, in the unit of this measurement; for two measurements of an affine family, a temperature, in the
     * matching unit of its family of differences.
     */
    public function subtract(self $other): self
    {
        $difference = $this->unit->difference;

        if (!$difference instanceof Unit) {
            return $this->add($other->negate());
        }

        if ($other->unit->family === $difference->family) {
            return new self($this->value->subtract($other->convertTo($difference)->value), $this->unit);
        }

        $this->assertSameFamily($other->unit);

        return new self($this->value->subtract($other->convertTo($this->unit)->value), $difference);
    }

    /**
     * The measurement multiplied by a decimal or a fraction (`2`, `0.5`, `1/3`), in an additive family only.
     */
    public function multiply(string $factor): self
    {
        $this->assertAdditive();

        return new self($this->value->multiply(Fraction::parse($factor)), $this->unit);
    }

    /**
     * The opposite value, in an additive family only.
     */
    public function negate(): self
    {
        $this->assertAdditive();

        return new self($this->value->negate(), $this->unit);
    }

    /**
     * -1, 0 or 1 as this measurement is below, equal to or above the other, both read in the standard unit of their
     * family: for fuel consumption, in litres per 100 km, so that 30 miles per gallon is above 40.
     */
    public function compareTo(self $other): int
    {
        $this->assertSameFamily($other->unit);

        return $this->unit->toStandard($this->value)->compare($other->unit->toStandard($other->value));
    }

    public function isZero(): bool
    {
        return 0 === $this->value->sign();
    }

    public function isNegative(): bool
    {
        return 0 > $this->value->sign();
    }

    /**
     * The measurement rounded to `$fractionDigits` digits after the point, in its unit.
     */
    public function round(int $fractionDigits, RoundingMode $mode = RoundingMode::HalfEven): self
    {
        $rounded = $this->value->round($fractionDigits, $mode);

        if ($this->unit->reciprocal && 0 === $rounded->sign()) {
            throw InvalidMeasurement::divisionByZero();
        }

        return new self($rounded, $this->unit);
    }

    /**
     * The value as a decimal of `$maximumFractionDigits` digits after the point at most, trailing zeros removed:
     * `0.45359237` kilograms to 3 digits is `0.454`, 2.50 to 3 digits is `2.5`.
     */
    public function toDecimal(int $maximumFractionDigits, RoundingMode $mode = RoundingMode::HalfEven): string
    {
        return $this->value->toTrimmedDecimal($maximumFractionDigits, $mode);
    }

    /**
     * The value as a decimal of exactly `$fractionDigits` digits after the point: `2.50` to 2 digits.
     */
    public function toFixedDecimal(int $fractionDigits, RoundingMode $mode = RoundingMode::HalfEven): string
    {
        return $this->value->toDecimal($fractionDigits, $mode);
    }

    /**
     * The exact value as a decimal, or `null` when its decimal never ends: one kilogram in pounds is
     * 1/0.45359237.
     */
    public function exactDecimal(): ?string
    {
        return $this->value->isTerminating() ? $this->value->toCanonicalDecimal() : null;
    }

    /**
     * The measurement split over several units of its family, from the largest to the smallest, as a height is
     * written in feet and inches: `70.5 [in_i]` over feet and inches is `5 [ft_i]` and `10.5 [in_i]`. Every part but
     * the last is a whole number, and the last takes what remains, exactly. A negative measurement gives negative
     * parts.
     *
     * @return non-empty-list<self>
     */
    public function split(Unit $largest, Unit ...$smaller): array
    {
        return $this->splitRounded([$largest, ...array_values($smaller)], null);
    }

    /**
     * `split()` over a list of units, with the measurement rounded first to `$fractionDigits` digits of its last unit
     * when given, so that the parts never show 12 inches.
     *
     * @param non-empty-list<Unit> $units
     *
     * @return non-empty-list<self>
     */
    public function splitRounded(array $units, ?int $fractionDigits, RoundingMode $mode = RoundingMode::HalfEven): array
    {
        $last = array_last($units);
        $rest = $this->convertTo($last);

        if (null !== $fractionDigits) {
            $rest = $rest->round($fractionDigits, $mode);
        }

        if (1 < \count($units)) {
            $this->assertAdditive();
        }

        $negative = $rest->isNegative();
        $rest = new self($rest->value->abs(), $last);
        $parts = [];

        foreach (\array_slice($units, 0, -1) as $unit) {
            $inUnit = $rest->convertTo($unit);
            $whole = $inUnit->value->round(0, RoundingMode::TowardsZero);
            $parts[] = new self($negative ? $whole->negate() : $whole, $unit);
            $rest = $rest->subtract(new self($whole, $unit));
        }

        $parts[] = new self($negative ? $rest->value->negate() : $rest->value, $last);

        return $parts;
    }

    /**
     * The measurement as `<decimal> <code>`, `12.5 kg`, which `UnitRegistry::parse()` reads back: exact when the
     * decimal has 18 significant digits or fewer, rounded half to even to 18 otherwise.
     */
    public function toString(): string
    {
        return $this->value->toCanonicalDecimal().' '.$this->unit->code;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @internal
     */
    public function value(): Fraction
    {
        return $this->value;
    }

    private function assertSameFamily(Unit $unit): void
    {
        if ($unit->family !== $this->unit->family) {
            throw IncompatibleUnits::families($this->unit->family, $unit->family);
        }
    }

    private function assertAdditive(): void
    {
        if (!$this->unit->additive) {
            throw IncompatibleUnits::notAdditive($this->unit->family);
        }
    }
}
