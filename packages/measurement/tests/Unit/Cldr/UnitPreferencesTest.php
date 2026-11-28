<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Unit\Cldr;

use Phil\Measurement\Cldr\UnitPreferences;
use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Measurement;
use Phil\Measurement\UnitRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(UnitPreferences::class)]
final class UnitPreferencesTest extends TestCase
{
    private UnitRegistry $registry;

    private UnitPreferences $preferences;

    protected function setUp(): void
    {
        $this->registry = UnitRegistry::standard();
        $this->preferences = UnitPreferences::cldr($this->registry);
    }

    /**
     * @return iterable<string, array{string, string, string, string, ?int, list<string>}>
     */
    public static function preferred(): iterable
    {
        yield 'height in the United States' => ['178', 'cm', 'person-height', 'US', 0, ['5 [ft_i]', '10 [in_i]']];
        yield 'height in France' => ['178', 'cm', 'person-height', 'FR', 0, ['1 m', '78 cm']];
        yield 'height in Canada' => ['178', 'cm', 'person-height', 'CA', 0, ['5 [ft_i]', '10 [in_i]']];
        yield 'road in the United Kingdom' => ['42', 'km', 'road', 'GB', 1, ['26.1 [mi_i]']];
        yield 'road in France' => ['42', 'km', 'road', 'FR', 1, ['42 km']];
        yield 'short road distance' => ['0.5', 'km', 'road', 'FR', 0, ['500 m']];
        yield 'parcel in the United States' => ['2.3', 'kg', 'default', 'US', 2, ['5.07 [lb_av]']];
        yield 'light parcel in the United States' => ['200', 'g', 'default', 'US', 1, ['7.1 [oz_av]']];
        yield 'parcel in Canada' => ['2.3', 'kg', 'default', 'CA', 2, ['2.3 kg']];
        yield 'heavy freight' => ['2300', 'kg', 'default', 'DE', 1, ['2.3 t']];
        yield 'unknown usage falls back to default' => ['2300', 'kg', 'cargo-hold', 'DE', 1, ['2.3 t']];
        yield 'unknown region takes the world' => ['2.3', 'kg', 'default', 'ZZ', 2, ['2.3 kg']];
        yield 'world region' => ['2300', 'g', 'default', '001', null, ['2.3 kg']];
        // The microgram, last in CLDR, is not in the registry: the milligram is the last candidate it holds.
        yield 'below every threshold' => ['0.0000005', 'g', 'default', '001', null, ['0.0005 mg']];
        yield 'person weight in the United Kingdom' => ['80', 'kg', 'person', 'GB', 0, ['12 [stone_av]', '8 [lb_av]']];
        yield 'temperature in the United States' => ['-20', 'Cel', 'default', 'US', 0, ['-4 [degF]']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('preferred')]
    public function testItPicksThePreferredUnits(string $value, string $unit, string $usage, string $region, ?int $digits, array $expected): void
    {
        $parts = $this->preferences->preferred($this->registry->measurement($value, $unit), $usage, $region, $digits);

        self::assertSame($expected, array_map(strval(...), $parts));
    }

    public function testTheRoundingModeIsTheCallers(): void
    {
        $parts = $this->preferences->preferred($this->registry->measurement('2.3', 'kg'), 'default', 'US', 1, RoundingMode::TowardsZero);

        self::assertSame(['5 [lb_av]'], array_map(strval(...), $parts));
    }

    public function testDefaultsAreTheWorldsDefaultUsage(): void
    {
        self::assertSame(['2.3 kg'], array_map(strval(...), $this->preferences->preferred($this->registry->measurement('2300', 'g'))));
        self::assertSame('kg', $this->preferences->units($this->registry->measurement('2300', 'g'))[0]->code ?? null);
    }

    public function testAFamilyWithoutCldrPreferencesIsLeftAsItIs(): void
    {
        $registry = $this->registry->withDefinitions(['paper-weight' => ['standard' => 'g/m2', 'units' => ['g/m2' => ['factor' => '1']]]]);
        $grammage = $registry->measurement('80', 'g/m2');
        $preferences = UnitPreferences::cldr($registry);

        self::assertSame([$grammage], $preferences->preferred($grammage, 'default', 'US'));
        self::assertNull($preferences->units($grammage));
    }

    public function testACategoryCldrDoesNotHoldIsLeftAsItIs(): void
    {
        $preferences = new UnitPreferences($this->registry, []);

        self::assertNull($preferences->units($this->registry->measurement('2', 'kg')));
    }

    public function testPreferredUnitsTheRegistryLacksAreSkipped(): void
    {
        $registry = UnitRegistry::fromDefinitions(['mass' => ['standard' => 'kg', 'cldr' => 'mass', 'units' => ['kg' => ['factor' => '1', 'cldr' => 'mass-kilogram']]]]);
        $preferences = new UnitPreferences($registry, ['mass' => [
            'default' => ['US' => [['units' => ['mass-pound']], ['units' => ['mass-pound', 'mass-kilogram']]]],
        ]]);

        self::assertNull($preferences->units($registry->measurement('2', 'kg'), 'default', 'US'));
    }

    public function testAUsageFallsBackThroughItsParents(): void
    {
        $preferences = new UnitPreferences($this->registry, ['length' => [
            'person' => ['001' => [['units' => ['length-meter']]]],
            'default' => ['001' => [['units' => ['length-kilometer']]]],
        ]]);

        self::assertSame('m', $preferences->units($this->registry->measurement('2', 'm'), 'person-height-informal')[0]->code ?? null);
        self::assertSame('km', $preferences->units($this->registry->measurement('2', 'm'), 'road-informal')[0]->code ?? null);
        self::assertSame('km', $preferences->units($this->registry->measurement('2', 'm'), 'default')[0]->code ?? null);
    }

    public function testATemperatureBelowZeroIsComparedByItsMagnitude(): void
    {
        $preferences = new UnitPreferences($this->registry, ['temperature' => [
            'default' => ['001' => [['units' => ['temperature-celsius'], 'geq' => '5'], ['units' => ['temperature-kelvin']]]],
        ]]);

        self::assertSame('Cel', $preferences->units($this->registry->measurement('-10', 'Cel'))[0]->code ?? null);
        self::assertSame('K', $preferences->units($this->registry->measurement('-4.99', 'Cel'))[0]->code ?? null);
        self::assertSame('Cel', $preferences->units($this->registry->measurement('5', 'Cel'))[0]->code ?? null);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidUsages(): iterable
    {
        yield 'uppercase usage' => ['Road', 'US'];
        yield 'usage with a slash' => ['../road', 'US'];
        yield 'usage with a line break' => ["road\n", 'US'];
        yield 'too many parts' => ['a-b-c-d-e', 'US'];
        yield 'lowercase region' => ['road', 'us'];
        yield 'region of three letters' => ['road', 'USA'];
        yield 'region with a line break' => ['road', "US\n"];
    }

    #[DataProvider('invalidUsages')]
    public function testItRefusesAnInvalidUsageOrRegion(string $usage, string $region): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains('A usage is lowercase words joined by dashes');
        $this->preferences->units(Measurement::of('1', $this->registry->unit('m')), $usage, $region);
    }

    public function testAUsageOfFourPartsIsAccepted(): void
    {
        self::assertNotNull($this->preferences->units(Measurement::of('1', $this->registry->unit('m')), 'a-b-c-d', '419'));
    }
}
