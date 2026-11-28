<?php

declare(strict_types=1);

namespace Phil\Measurement;

use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Exception\InvalidUnitDefinition;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\Exception\UnknownUnit;

/**
 * The families of units and their units, built from definitions in data rather than from a class per quantity.
 *
 * `standard()` holds the families the library declares in `data/units.php`: length, mass, volume, area, temperature
 * and temperature difference, duration, speed, fuel consumption, energy, pressure and power. `withDefinitions()` adds
 * families and units to them, from an application's configuration:
 *
 * ```php
 * $registry = UnitRegistry::standard()->withDefinitions([
 *     'mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891']]],
 *     'paper-weight' => [
 *         'standard' => 'g/m2',
 *         'units' => ['g/m2' => ['factor' => '1'], '[lb_av]/[ream]' => ['factor' => '1.48']],
 *     ],
 * ]);
 * ```
 *
 * A family names its `standard` unit, whose factor is 1, and its `units` by code; optionally its CLDR category
 * (`cldr`) and the family of its differences (`difference`). A unit gives its `factor` to the standard unit as a
 * decimal or a fraction of integers (`5/9`), and optionally an `offset` (temperature scales), `reciprocal: true` (a unit
 * that varies the other way), its `ucum` exchange code when it differs from its code, its `cldr` key, and the unit of
 * its `difference`.
 *
 * The registry is immutable: `withDefinitions()` returns a new one, and refuses a code defined twice. Its size is
 * bounded, so that definitions read from outside cannot grow without end.
 */
final readonly class UnitRegistry
{
    /** The families a registry holds at most. */
    public const int MAXIMUM_FAMILIES = 64;

    /** The units a registry holds at most, over every family. */
    public const int MAXIMUM_UNITS = 1024;

    private const array FAMILY_KEYS = ['standard', 'units', 'cldr', 'difference'];

    private const array UNIT_KEYS = ['factor', 'offset', 'reciprocal', 'ucum', 'cldr', 'difference'];

    /**
     * @param array<string, UnitFamily>                                                                                                              $families
     * @param array<string, Unit>                                                                                                                    $units
     * @param array<string, array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null}> $definitions
     */
    private function __construct(
        private array $families,
        private array $units,
        private array $definitions,
    ) {
    }

    /**
     * The families and units the library declares.
     */
    public static function standard(): self
    {
        /** @var array<string, mixed> $definitions */
        $definitions = require \dirname(__DIR__).'/data/units.php';

        return self::fromDefinitions($definitions);
    }

    /**
     * A registry of these definitions only.
     *
     * @param array<mixed> $definitions
     */
    public static function fromDefinitions(array $definitions): self
    {
        return self::build(self::normalized([], $definitions));
    }

    /**
     * A new registry with these families added, and these units added to families it already holds.
     *
     * @param array<mixed> $definitions
     */
    public function withDefinitions(array $definitions): self
    {
        return self::build(self::normalized($this->definitions, $definitions));
    }

    public function has(string $code): bool
    {
        return isset($this->units[$code]);
    }

    public function unit(string $code): Unit
    {
        return $this->units[$code] ?? throw UnknownUnit::code($code);
    }

    public function family(string $name): UnitFamily
    {
        return $this->families[$name] ?? throw UnknownUnit::family($name);
    }

    /**
     * @return array<string, UnitFamily>
     */
    public function families(): array
    {
        return $this->families;
    }

    /**
     * The unit with this UCUM exchange code, within `$family` when given; a code shared by units of several families,
     * such as `Cel`, needs the family.
     */
    public function fromUcum(string $ucum, ?string $family = null): Unit
    {
        $found = null;

        foreach ($this->units as $unit) {
            if ($ucum !== $unit->ucum || (null !== $family && $family !== $unit->family)) {
                continue;
            }

            if (null !== $found) {
                throw UnknownUnit::ambiguousUcum($ucum);
            }

            $found = $unit;
        }

        return $found ?? throw UnknownUnit::ucum($ucum);
    }

    /**
     * A measurement from a decimal and a unit code: `measurement('12.5', 'kg')`.
     */
    public function measurement(string $value, string $code): Measurement
    {
        return Measurement::of($value, $this->unit($code));
    }

    /**
     * Reads a measurement as `Measurement::toString()` writes it: a decimal, one space and a unit code, `12.5 kg`.
     */
    public function parse(string $text): Measurement
    {
        $space = strpos($text, ' ');

        if (false === $space || 0 === $space || 200 < \strlen($text)) {
            throw InvalidMeasurement::notAMeasurement($text);
        }

        return $this->measurement(substr($text, 0, $space), substr($text, $space + 1));
    }

    /**
     * The definitions this registry was built from, which `withDefinitions()` extends.
     *
     * @return array<string, array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null}>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /**
     * @param array<string, array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null}> $known
     * @param array<mixed>                                                                                                                           $added
     *
     * @return array<string, array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null}>
     */
    private static function normalized(array $known, array $added): array
    {
        foreach ($added as $name => $family) {
            if (!\is_string($name) || 1 !== preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $name)) {
                throw InvalidUnitDefinition::because('A family is named by lowercase letters, digits and dashes, 64 at most, starting with a letter.');
            }

            if (!\is_array($family) || [] !== array_diff(array_keys($family), self::FAMILY_KEYS)) {
                throw InvalidUnitDefinition::because(\sprintf('The family "%s" is defined by %s only.', $name, implode(', ', self::FAMILY_KEYS)));
            }

            $known[$name] = self::units($name, $family, self::settings($name, $family, $known[$name] ?? null));
        }

        return $known;
    }

    /**
     * The family's entry with the settings it gives: a family the registry holds keeps its own.
     *
     * @param array<mixed>                                                                                                                 $family
     * @param array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null}|null $known
     *
     * @return array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null}
     */
    private static function settings(string $name, array $family, ?array $known): array
    {
        $entry = $known ?? ['units' => []];

        foreach (['standard', 'cldr', 'difference'] as $key) {
            if (!\array_key_exists($key, $family)) {
                continue;
            }

            $value = $family[$key];

            if (null !== $value && !\is_string($value)) {
                throw InvalidUnitDefinition::because(\sprintf('The "%s" of the family "%s" is a string.', $key, $name));
            }

            if (null !== $known && ($known[$key] ?? null) !== $value) {
                throw InvalidUnitDefinition::because(\sprintf('The family "%s" already has its "%s": added units cannot change it.', $name, $key));
            }

            $entry[$key] = $value;
        }

        return $entry;
    }

    /**
     * The family's entry with the units it gives added.
     *
     * @param array<mixed>                                                                                                            $family
     * @param array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null} $entry
     *
     * @return array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null}
     */
    private static function units(string $name, array $family, array $entry): array
    {
        $units = $family['units'] ?? null;

        if (!\is_array($units) || [] === $units) {
            throw InvalidUnitDefinition::because(\sprintf('The family "%s" defines one unit or more under "units".', $name));
        }

        foreach ($units as $code => $unit) {
            if (!\is_string($code) || 1 !== preg_match('/^[!-~]{1,64}$/D', $code)) {
                throw InvalidUnitDefinition::because(\sprintf('A unit code of the family "%s" is 1 to 64 printable ASCII characters, without spaces.', $name));
            }

            if (!\is_array($unit) || [] !== array_diff(array_keys($unit), self::UNIT_KEYS)) {
                throw InvalidUnitDefinition::because(\sprintf('The unit "%s" is defined by %s only.', $code, implode(', ', self::UNIT_KEYS)));
            }

            if (isset($entry['units'][$code])) {
                throw InvalidUnitDefinition::because(\sprintf('The unit "%s" is already defined.', $code));
            }

            /** @var array<string, mixed> $unit */
            $entry['units'][$code] = $unit;
        }

        return $entry;
    }

    /**
     * @param array<string, array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null}> $definitions
     */
    private static function build(array $definitions): self
    {
        if (self::MAXIMUM_FAMILIES < \count($definitions)) {
            throw InvalidUnitDefinition::because(\sprintf('A registry holds %d families at most.', self::MAXIMUM_FAMILIES));
        }

        if (self::MAXIMUM_UNITS < array_sum(array_map(static fn (array $family): int => \count($family['units']), $definitions))) {
            throw InvalidUnitDefinition::because(\sprintf('A registry holds %d units at most.', self::MAXIMUM_UNITS));
        }

        $families = [];
        $units = [];

        // The families of differences first: the units of an affine family point at theirs.
        uasort($definitions, static fn (array $left, array $right): int => (null === ($left['difference'] ?? null) ? 0 : 1) <=> (null === ($right['difference'] ?? null) ? 0 : 1));

        foreach ($definitions as $name => $definition) {
            $difference = $definition['difference'] ?? null;

            if (null !== $difference && (!isset($families[$difference]) || null !== $families[$difference]->difference)) {
                throw InvalidUnitDefinition::because(\sprintf('The family "%s" measures its differences in "%s", a family of the registry without differences of its own.', $name, $difference));
            }

            $family = self::buildFamily($name, $definition, null === $difference ? null : $families[$difference], $units);
            $families[$name] = $family;
            $units += $family->units;
        }

        ksort($families);

        return new self($families, $units, $definitions);
    }

    /**
     * @param array{standard?: string|null, units: array<string, array<string, mixed>>, cldr?: string|null, difference?: string|null} $definition
     * @param array<string, Unit>                                                                                                     $known      the units of the families already built
     */
    private static function buildFamily(string $name, array $definition, ?UnitFamily $differences, array $known): UnitFamily
    {
        $standard = $definition['standard'] ?? null;

        if (null === $standard || !isset($definition['units'][$standard])) {
            throw InvalidUnitDefinition::because(\sprintf('The family "%s" names its standard unit, one of its units.', $name));
        }

        $parsed = [];
        $additive = !$differences instanceof UnitFamily;

        foreach ($definition['units'] as $code => $unit) {
            if (isset($known[$code])) {
                throw InvalidUnitDefinition::because(\sprintf('The unit "%s" is already defined in the family "%s".', $code, $known[$code]->family));
            }

            $parsed[$code] = self::parts($code, $unit);
            $additive = $additive && !$parsed[$code]['reciprocal'] && 0 === $parsed[$code]['offset']->sign();
        }

        $units = [];

        foreach ($parsed as $code => $parts) {
            $units[$code] = new Unit(
                $code,
                $name,
                $parts['ucum'] ?? $code,
                $parts['cldr'],
                $parts['factor'],
                $parts['offset'],
                $parts['reciprocal'],
                $additive,
                self::difference($code, $parts, $differences),
            );
        }

        if (!$units[$standard]->isStandard()) {
            throw InvalidUnitDefinition::because(\sprintf('The standard unit "%s" has a factor of 1, no offset, and is not reciprocal.', $standard));
        }

        $units = [$standard => $units[$standard]] + $units;

        return new UnitFamily($name, $units[$standard], $units, $definition['cldr'] ?? null, $differences?->name);
    }

    /**
     * @param array<string, mixed> $unit
     *
     * @return array{factor: Fraction, offset: Fraction, reciprocal: bool, ucum: ?string, cldr: ?string, difference: ?string}
     */
    private static function parts(string $code, array $unit): array
    {
        $factor = self::number($code, 'factor', $unit['factor'] ?? null);
        $offset = self::number($code, 'offset', $unit['offset'] ?? '0');
        $reciprocal = $unit['reciprocal'] ?? false;

        if (0 >= $factor->sign()) {
            throw InvalidUnitDefinition::because(\sprintf('The factor of the unit "%s" is above zero.', $code));
        }

        if (!\is_bool($reciprocal) || ($reciprocal && 0 !== $offset->sign())) {
            throw InvalidUnitDefinition::because(\sprintf('"reciprocal" is true or false, and a reciprocal unit, "%s", has no offset.', $code));
        }

        $strings = [];

        foreach (['ucum', 'cldr', 'difference'] as $key) {
            $value = $unit[$key] ?? null;

            if (null !== $value && (!\is_string($value) || 1 !== preg_match('/^[!-~]{1,64}$/D', $value))) {
                throw InvalidUnitDefinition::because(\sprintf('The "%s" of the unit "%s" is 1 to 64 printable ASCII characters.', $key, $code));
            }

            $strings[$key] = $value;
        }

        return ['factor' => $factor, 'offset' => $offset, 'reciprocal' => $reciprocal] + $strings;
    }

    private static function number(string $code, string $key, mixed $value): Fraction
    {
        if (!\is_string($value)) {
            throw InvalidUnitDefinition::because(\sprintf('The %s of the unit "%s" is a string: a decimal or a fraction of integers.', $key, $code));
        }

        try {
            return Fraction::parse($value);
        } catch (MeasurementException $measurementException) {
            throw InvalidUnitDefinition::because(\sprintf('The %s of the unit "%s" is not an exact number: %s', $key, $code, $measurementException->getMessage()));
        }
    }

    /**
     * The unit of differences of a unit of an affine family: named by the unit, with the same factor.
     *
     * @param array{factor: Fraction, offset: Fraction, reciprocal: bool, ucum: ?string, cldr: ?string, difference: ?string} $parts
     */
    private static function difference(string $code, array $parts, ?UnitFamily $differences): ?Unit
    {
        if (!$differences instanceof UnitFamily) {
            if (null !== $parts['difference']) {
                throw InvalidUnitDefinition::because(\sprintf('The unit "%s" names a unit of differences, but its family has no "difference" family.', $code));
            }

            return null;
        }

        $difference = null === $parts['difference'] ? null : ($differences->units[$parts['difference']] ?? null);

        if (null === $difference || 0 !== $parts['factor']->compare(Fraction::parse($difference->factor()))) {
            throw InvalidUnitDefinition::because(\sprintf('The unit "%s" names its unit of differences, a unit of the family "%s" with the same factor.', $code, $differences->name));
        }

        return $difference;
    }
}
