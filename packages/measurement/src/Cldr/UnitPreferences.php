<?php

declare(strict_types=1);

namespace Phil\Measurement\Cldr;

use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Fraction;
use Phil\Measurement\Measurement;
use Phil\Measurement\Unit;
use Phil\Measurement\UnitFamily;
use Phil\Measurement\UnitRegistry;
use RoundingMode;

/**
 * The unit a region writes a quantity in, for a usage, from the unit preferences of CLDR: a person's height in feet
 * and inches in the United States and in centimetres in France, a road distance in miles in the United Kingdom and in
 * kilometres elsewhere, short distances in metres, a parcel in pounds or kilograms.
 *
 * ```php
 * $preferences = UnitPreferences::cldr($registry);
 * $preferences->preferred($height, usage: 'person-height', region: 'US');   // [5 [ft_i], 10 [in_i]]
 * $preferences->preferred($trip, usage: 'road', region: 'FR');              // [12.3 km]
 * ```
 *
 * The candidates of a usage are tried in order: the first one the magnitude of the measurement reaches, from its
 * threshold (`geq`), or from 1 without one, is chosen, and the last one otherwise. A usage falls back to its parent
 * (`person-height` to `person`) and then to `default`; a region without preferences of its own takes the world's
 * (`001`). A preferred unit the registry does not hold is skipped. A family without a CLDR
 * category keeps its measurement as it is.
 */
final readonly class UnitPreferences
{
    /**
     * @param array<string, array<string, array<string, list<array{units: list<string>, geq?: string}>>>> $preferences
     */
    public function __construct(
        private UnitRegistry $registry,
        private array $preferences,
    ) {
    }

    /**
     * The preferences of the CLDR release in `data/cldr/VERSION`.
     */
    public static function cldr(UnitRegistry $registry): self
    {
        /** @var array<string, array<string, array<string, list<array{units: list<string>, geq?: string}>>>> $preferences */
        $preferences = require \dirname(__DIR__, 2).'/data/cldr/preferences.php';

        return new self($registry, $preferences);
    }

    /**
     * The measurement in the preferred unit, or split over the preferred units (feet and inches), the last part
     * rounded to `$fractionDigits` when given.
     *
     * @return non-empty-list<Measurement>
     */
    public function preferred(Measurement $measurement, string $usage = 'default', string $region = '001', ?int $fractionDigits = null, RoundingMode $mode = RoundingMode::HalfEven): array
    {
        $units = $this->units($measurement, $usage, $region);

        return null === $units ? [$measurement] : $measurement->splitRounded($units, $fractionDigits, $mode);
    }

    /**
     * The preferred unit or units for the measurement, or `null` when CLDR has none the registry holds.
     *
     * @return non-empty-list<Unit>|null
     */
    public function units(Measurement $measurement, string $usage = 'default', string $region = '001'): ?array
    {
        if (1 !== preg_match('/^[a-z0-9]{1,32}(?:-[a-z0-9]{1,32}){0,3}$/D', $usage) || 1 !== preg_match('/^(?:[A-Z]{2}|\d{3})$/D', $region)) {
            throw InvalidMeasurement::because('A usage is lowercase words joined by dashes ("person-height"), and a region an ISO 3166-1 alpha-2 code or a UN M49 number ("001").');
        }

        $family = $this->registry->family($measurement->unit->family);
        $category = null === $family->cldr ? [] : ($this->preferences[$family->cldr] ?? []);

        foreach ($this->usages($usage) as $candidateUsage) {
            $regions = $category[$candidateUsage] ?? null;
            $candidates = null === $regions ? [] : $this->known($family, $regions[$region] ?? $regions['001'] ?? []);

            if ([] !== $candidates) {
                return $this->chosen($measurement, $candidates);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function usages(string $usage): array
    {
        $usages = [$usage];

        while (false !== ($dash = strrpos($usage, '-'))) {
            $usage = substr($usage, 0, $dash);
            $usages[] = $usage;
        }

        return 'default' === $usage ? $usages : [...$usages, 'default'];
    }

    /**
     * The candidates whose units the family holds, as units.
     *
     * @param list<array{units: list<string>, geq?: string}> $candidates
     *
     * @return list<array{units: non-empty-list<Unit>, geq: ?string}>
     */
    private function known(UnitFamily $family, array $candidates): array
    {
        $known = [];

        foreach ($candidates as $candidate) {
            $units = array_map($family->unitForCldr(...), $candidate['units']);

            if ([] !== $units && !\in_array(null, $units, true)) {
                /** @var non-empty-list<Unit> $units */
                $known[] = ['units' => $units, 'geq' => $candidate['geq'] ?? null];
            }
        }

        return $known;
    }

    /**
     * The first candidate the measurement reaches, in the first of its units, or the last one. A candidate without a
     * threshold is reached from 1, as CLDR defines it.
     *
     * @param non-empty-list<array{units: non-empty-list<Unit>, geq: ?string}> $candidates
     *
     * @return non-empty-list<Unit>
     */
    private function chosen(Measurement $measurement, array $candidates): array
    {
        foreach ($candidates as $candidate) {
            // The magnitude, read on the value itself: a temperature below zero has no opposite in its family.
            $magnitude = $measurement->convertTo($candidate['units'][0])->value()->abs();

            if (0 <= $magnitude->compare(Fraction::parse($candidate['geq'] ?? '1'))) {
                return $candidate['units'];
            }
        }

        return array_last($candidates)['units'];
    }
}
