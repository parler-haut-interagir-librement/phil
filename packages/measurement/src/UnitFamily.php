<?php

declare(strict_types=1);

namespace Phil\Measurement;

/**
 * A family of units that measure the same quantity — length, mass, temperature — and convert into each other through
 * its standard unit.
 *
 * `cldr` is the category of the family in the CLDR data (`length`, `consumption`), which its unit preferences are read
 * from. `difference` names the family that measures the differences of an affine family: a temperature minus a
 * temperature is a temperature difference.
 */
final readonly class UnitFamily
{
    /**
     * @param array<string, Unit> $units by code, the standard unit first
     *
     * @internal built by UnitRegistry
     */
    public function __construct(
        public string $name,
        public Unit $standard,
        public array $units,
        public ?string $cldr = null,
        public ?string $difference = null,
    ) {
    }

    public function has(string $code): bool
    {
        return isset($this->units[$code]);
    }

    /**
     * The unit of the family that CLDR names `$key` (`mass-pound`), or `null`.
     */
    public function unitForCldr(string $key): ?Unit
    {
        foreach ($this->units as $unit) {
            if ($key === $unit->cldr) {
                return $unit;
            }
        }

        return null;
    }
}
