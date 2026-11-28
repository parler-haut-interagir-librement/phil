<?php

declare(strict_types=1);

namespace Phil\Measurement;

use Phil\Measurement\Exception\InvalidMeasurement;

/**
 * A unit of a family, and how a value in it becomes a value in the standard unit of its family.
 *
 * Most units are linear: `standard = value × factor`. A temperature scale adds an offset, `standard = value × factor +
 * offset`, and its differences are measured in a unit of another family, without the offset. A reciprocal unit varies
 * the other way, `standard = factor ÷ value`: miles per gallon go up as litres per 100 km go down.
 *
 * The code is the unit's identifier in the registry, in UCUM syntax; `ucum` is the code exchanged with other systems,
 * the same except for a unit that only differs from another by its family, such as a temperature difference (`Cel` for
 * `Cel{difference}`). `cldr` names the unit in the CLDR data, for its preferences and its patterns.
 *
 * Units are built by `UnitRegistry` from their definitions.
 */
final readonly class Unit
{
    /**
     * @internal built by UnitRegistry
     */
    public function __construct(
        public string $code,
        public string $family,
        public string $ucum,
        public ?string $cldr,
        private Fraction $factor,
        private Fraction $offset,
        public bool $reciprocal,
        public bool $additive,
        public ?self $difference = null,
    ) {
    }

    /**
     * The factor to the standard unit, exactly: `0.45359237` for the pound, `5/9` for the degree Fahrenheit.
     */
    public function factor(): string
    {
        return $this->written($this->factor);
    }

    /**
     * The offset to the standard unit, exactly: `273.15` for the degree Celsius, `0` for a linear unit.
     */
    public function offset(): string
    {
        return $this->written($this->offset);
    }

    public function isStandard(): bool
    {
        return !$this->reciprocal && 0 === $this->offset->sign() && 0 === $this->factor->compare(Fraction::of(1));
    }

    /**
     * @internal
     */
    public function toStandard(Fraction $value): Fraction
    {
        if ($this->reciprocal) {
            return 0 === $value->sign() ? throw InvalidMeasurement::divisionByZero() : $this->factor->divide($value);
        }

        $standard = $value->multiply($this->factor);

        // Most units have no offset: the sum, and the reductions it costs, are spared.
        return 0 === $this->offset->sign() ? $standard : $standard->add($this->offset);
    }

    /**
     * @internal
     */
    public function fromStandard(Fraction $value): Fraction
    {
        if ($this->reciprocal) {
            return 0 === $value->sign() ? throw InvalidMeasurement::divisionByZero() : $this->factor->divide($value);
        }

        return (0 === $this->offset->sign() ? $value : $value->subtract($this->offset))->divide($this->factor);
    }

    private function written(Fraction $number): string
    {
        return $number->isTerminating()
            ? $number->toCanonicalDecimal()
            : $number->numerator.'/'.$number->denominator;
    }
}
