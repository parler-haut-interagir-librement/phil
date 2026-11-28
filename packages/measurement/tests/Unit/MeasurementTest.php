<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Unit;

use Phil\Measurement\Exception\IncompatibleUnits;
use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Fraction;
use Phil\Measurement\Measurement;
use Phil\Measurement\Unit;
use Phil\Measurement\UnitRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(Measurement::class)]
#[CoversClass(Unit::class)]
#[CoversClass(Fraction::class)]
#[CoversClass(IncompatibleUnits::class)]
#[CoversClass(InvalidMeasurement::class)]
final class MeasurementTest extends TestCase
{
    private UnitRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = UnitRegistry::standard();
    }

    /**
     * @return iterable<string, array{string, string, string, int, string}>
     */
    public static function conversions(): iterable
    {
        yield 'pound to kilogram' => ['1', '[lb_av]', 'kg', 8, '0.45359237'];
        yield 'kilogram to pound' => ['1', 'kg', '[lb_av]', 10, '2.2046226218'];
        yield 'inch to centimetre' => ['1', '[in_i]', 'cm', 4, '2.54'];
        yield 'mile to kilometre' => ['26.2', '[mi_i]', 'km', 6, '42.164813'];
        yield 'gallon to litre' => ['1', '[gal_us]', 'L', 9, '3.785411784'];
        yield 'imperial gallon to US gallon' => ['1', '[gal_br]', '[gal_us]', 6, '1.20095'];
        yield 'acre to square metre' => ['1', '[acr_us]', 'm2', 4, '4046.8726'];
        yield 'celsius to fahrenheit' => ['100', 'Cel', '[degF]', 6, '212'];
        yield 'fahrenheit to celsius' => ['-40', '[degF]', 'Cel', 6, '-40'];
        yield 'celsius to kelvin' => ['-273.15', 'Cel', 'K', 6, '0'];
        yield 'rankine to fahrenheit' => ['491.67', '[degR]', '[degF]', 6, '32'];
        yield 'difference in fahrenheit to celsius' => ['9', '[degF]{difference}', 'Cel{difference}', 6, '5'];
        yield 'miles per gallon to litres per 100 km' => ['30', '[mi_i]/[gal_us]', 'L/(100.km)', 4, '7.8405'];
        yield 'litres per 100 km to miles per gallon' => ['7.8405', 'L/(100.km)', '[mi_i]/[gal_us]', 2, '30'];
        yield 'imperial miles per gallon to US' => ['36', '[mi_i]/[gal_br]', '[mi_i]/[gal_us]', 3, '29.976'];
        yield 'kilometres per litre' => ['20', 'km/L', 'L/(100.km)', 6, '5'];
        yield 'kilometre per hour to metre per second' => ['36', 'km/h', 'm/s', 6, '10'];
        yield 'knot' => ['1', '[kn_i]', 'km/h', 3, '1.852'];
        yield 'psi to kilopascal' => ['1', '[psi]', 'kPa', 6, '6.894757'];
        yield 'food calorie to kilojoule' => ['100', '[Cal]', 'kJ', 6, '418.4'];
        yield 'week to hour' => ['1', 'wk', 'h', 6, '168'];
    }

    #[DataProvider('conversions')]
    public function testItConvertsExactly(string $value, string $from, string $to, int $digits, string $expected): void
    {
        $converted = $this->registry->measurement($value, $from)->convertTo($this->registry->unit($to));

        self::assertSame($expected, $converted->toDecimal($digits));
        self::assertSame($to, $converted->unit->code);
    }

    public function testAChainOfConversionsLosesNothing(): void
    {
        $pounds = $this->registry->measurement('2.75', '[lb_av]');
        $back = $pounds->convertTo($this->registry->unit('kg'))
            ->convertTo($this->registry->unit('[oz_av]'))
            ->convertTo($this->registry->unit('[stone_av]'))
            ->convertTo($this->registry->unit('[lb_av]'));

        self::assertSame('2.75', $back->exactDecimal());
        self::assertSame('44', $pounds->convertTo($this->registry->unit('[oz_av]'))->exactDecimal());
    }

    public function testConvertingToItsOwnUnitIsTheSameMeasurement(): void
    {
        $kilograms = $this->registry->measurement('2', 'kg');

        self::assertSame($kilograms, $kilograms->convertTo($this->registry->unit('kg')));
    }

    public function testItRefusesToConvertAcrossFamilies(): void
    {
        $this->expectException(IncompatibleUnits::class);
        $this->expectExceptionMessageIsOrContains('A measurement of the family "mass" cannot be turned into one of the family "length".');
        $this->registry->measurement('2', 'kg')->convertTo($this->registry->unit('m'));
    }

    public function testATemperatureIsNotADifference(): void
    {
        $this->expectException(IncompatibleUnits::class);
        $this->registry->measurement('2', 'Cel')->convertTo($this->registry->unit('Cel{difference}'));
    }

    public function testMeasurementsOfAFamilyAreAddedAndSubtracted(): void
    {
        $kilograms = $this->registry->measurement('1.5', 'kg');
        $grams = $this->registry->measurement('250', 'g');

        self::assertSame('1.75 kg', $kilograms->add($grams)->toString());
        self::assertSame('1.25 kg', $kilograms->subtract($grams)->toString());
        self::assertSame('-1250 g', $grams->subtract($kilograms)->toString());
        self::assertSame('-1.5 kg', $kilograms->negate()->toString());
        self::assertSame('0.5 kg', $kilograms->multiply('1/3')->toString());
        self::assertSame('3.75 kg', $kilograms->multiply('2.5')->toString());
    }

    public function testATemperatureTakesADifference(): void
    {
        $morning = $this->registry->measurement('10', 'Cel');
        $warming = $this->registry->measurement('9', '[degF]{difference}');

        self::assertSame('15 Cel', $morning->add($warming)->toString());
        self::assertSame('5 Cel', $morning->subtract($warming)->toString());
    }

    public function testTwoTemperaturesGiveADifference(): void
    {
        $noon = $this->registry->measurement('68', '[degF]');
        $morning = $this->registry->measurement('10', 'Cel');

        self::assertSame('18 [degF]{difference}', $noon->subtract($morning)->toString());
        self::assertSame('-10 Cel{difference}', $morning->subtract($noon)->toString());
    }

    public function testTwoTemperaturesAreNotAdded(): void
    {
        $this->expectException(IncompatibleUnits::class);
        $this->expectExceptionMessageIsOrContains('Measurements of the family "temperature" are not added together');
        $this->registry->measurement('10', 'Cel')->add($this->registry->measurement('10', 'Cel'));
    }

    public function testATemperatureIsNeitherNegatedNorMultiplied(): void
    {
        try {
            $this->registry->measurement('10', 'Cel')->negate();
            self::fail('A temperature has no opposite.');
        } catch (IncompatibleUnits) {
            $this->expectException(IncompatibleUnits::class);
            $this->registry->measurement('10', 'Cel')->multiply('2');
        }
    }

    public function testATemperatureMinusAnotherFamilyIsRefused(): void
    {
        $this->expectException(IncompatibleUnits::class);
        $this->expectExceptionMessageIsOrContains('family "temperature" cannot be turned into one of the family "mass"');
        $this->registry->measurement('10', 'Cel')->subtract($this->registry->measurement('1', 'kg'));
    }

    public function testAFuelConsumptionIsNotSummed(): void
    {
        $this->expectException(IncompatibleUnits::class);
        $this->expectExceptionMessageIsOrContains('family "fuel-consumption" are not added together');
        $this->registry->measurement('30', '[mi_i]/[gal_us]')->add($this->registry->measurement('7', 'L/(100.km)'));
    }

    public function testAMassAndALengthAreNotAdded(): void
    {
        $this->expectException(IncompatibleUnits::class);
        $this->registry->measurement('1', 'kg')->add($this->registry->measurement('1', 'm'));
    }

    public function testZeroIsRefusedInAReciprocalUnit(): void
    {
        try {
            $this->registry->measurement('0', '[mi_i]/[gal_us]');
            self::fail('Zero miles per gallon is refused.');
        } catch (InvalidMeasurement $invalidMeasurement) {
            self::assertStringContainsString('division by zero', $invalidMeasurement->getMessage());
        }

        $this->expectException(InvalidMeasurement::class);
        $this->registry->measurement('0', 'L/(100.km)')->convertTo($this->registry->unit('[mi_i]/[gal_us]'));
    }

    public function testRoundingAReciprocalMeasurementToZeroIsRefused(): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->registry->measurement('0.001', 'km/L')->round(2);
    }

    public function testATemperatureAndAMassAreRefusedAsDifferentFamilies(): void
    {
        $this->expectException(IncompatibleUnits::class);
        $this->expectExceptionMessageIsOrContains('family "temperature" cannot be turned into one of the family "mass"');
        $this->registry->measurement('10', 'Cel')->add($this->registry->measurement('1', 'kg'));
    }

    public function testRoundingToZeroIsRefusedOnlyInAReciprocalUnit(): void
    {
        self::assertSame('0', $this->registry->measurement('0.001', 'kg')->round(2)->toDecimal(2));
        self::assertSame('0.4 km/L', $this->registry->measurement('0.4', 'km/L')->round(1)->toString());
    }

    public function testAReciprocalUnitConvertsNegativeValuesToo(): void
    {
        self::assertSame('-7.84048611111111111 L/(100.km)', $this->registry->measurement('-30', '[mi_i]/[gal_us]')->convertTo($this->registry->unit('L/(100.km)'))->toString());
        self::assertSame('-29.4018229166666667 [mi_i]/[gal_us]', $this->registry->measurement('-8', 'L/(100.km)')->convertTo($this->registry->unit('[mi_i]/[gal_us]'))->toString());
        self::assertSame('-10 L/(100.km)', $this->registry->measurement('-10', 'km/L')->convertTo($this->registry->unit('L/(100.km)'))->toString());
    }

    public function testZeroIsRefusedWhateverWayItReachesAReciprocalUnit(): void
    {
        $this->expectException(InvalidMeasurement::class);
        Measurement::exact(Fraction::of(0), $this->registry->unit('km/L'))->convertTo($this->registry->unit('L/(100.km)'));
    }

    public function testAnOffsetBelowZeroIsAddedAndTakenOff(): void
    {
        $registry = UnitRegistry::fromDefinitions(['shift' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1'], 'b' => ['factor' => '2', 'offset' => '-3']]]]);

        self::assertSame('17 a', $registry->measurement('10', 'b')->convertTo($registry->unit('a'))->toString());
        self::assertSame('10 b', $registry->measurement('17', 'a')->convertTo($registry->unit('b'))->toString());
    }

    public function testComparisonReadsTheStandardUnit(): void
    {
        $thirty = $this->registry->measurement('30', '[mi_i]/[gal_us]');
        $forty = $this->registry->measurement('40', '[mi_i]/[gal_us]');
        $pound = $this->registry->measurement('1', '[lb_av]');

        self::assertSame(1, $thirty->compareTo($forty));
        self::assertSame(-1, $forty->compareTo($thirty));
        self::assertSame(0, $pound->compareTo($this->registry->measurement('16', '[oz_av]')));
        self::assertSame(-1, $pound->compareTo($this->registry->measurement('0.5', 'kg')));
        self::assertSame(1, $this->registry->measurement('0', 'Cel')->compareTo($this->registry->measurement('0', '[degF]')));
    }

    public function testComparisonAcrossFamiliesIsRefused(): void
    {
        $this->expectException(IncompatibleUnits::class);
        $this->registry->measurement('1', 'kg')->compareTo($this->registry->measurement('1', 'm'));
    }

    public function testItTellsZeroAndNegativeValues(): void
    {
        self::assertTrue($this->registry->measurement('0', 'kg')->isZero());
        self::assertFalse($this->registry->measurement('0.1', 'kg')->isZero());
        self::assertTrue($this->registry->measurement('-0.1', 'kg')->isNegative());
        self::assertFalse($this->registry->measurement('0', 'kg')->isNegative());
    }

    public function testItRoundsAndWritesDecimals(): void
    {
        $kilograms = $this->registry->measurement('1', '[lb_av]')->convertTo($this->registry->unit('kg'));

        self::assertSame('0.454', $kilograms->toDecimal(3));
        self::assertSame('0.453', $kilograms->toDecimal(3, RoundingMode::TowardsZero));
        self::assertSame('2.5', $this->registry->measurement('2.50', 'kg')->toDecimal(3));
        self::assertSame('2.500', $this->registry->measurement('2.5', 'kg')->toFixedDecimal(3));
        self::assertSame('2.6', $this->registry->measurement('2.55', 'kg')->toFixedDecimal(1, RoundingMode::HalfAwayFromZero));
        self::assertSame('0.45 kg', $kilograms->round(2)->toString());
        self::assertSame('0.46 kg', $kilograms->round(2, RoundingMode::AwayFromZero)->toString());
        self::assertSame('0.45359237', $kilograms->exactDecimal());
        self::assertNull($this->registry->measurement('1', 'kg')->convertTo($this->registry->unit('[lb_av]'))->exactDecimal());
        self::assertSame('2.20462262184877581 [lb_av]', (string) $this->registry->measurement('1', 'kg')->convertTo($this->registry->unit('[lb_av]')));
    }

    public function testItSplitsOverSeveralUnits(): void
    {
        $height = $this->registry->measurement('70.5', '[in_i]');
        $feet = $this->registry->unit('[ft_i]');
        $inches = $this->registry->unit('[in_i]');

        self::assertSame(['5 [ft_i]', '10.5 [in_i]'], array_map(strval(...), $height->split($feet, $inches)));
        self::assertSame(['-5 [ft_i]', '-10.5 [in_i]'], array_map(strval(...), $height->negate()->split($feet, $inches)));
        self::assertSame(['70.5 [in_i]'], array_map(strval(...), $height->split($inches)));
        self::assertSame(['0 [ft_i]', '11 [in_i]'], array_map(strval(...), $this->registry->measurement('11', '[in_i]')->split($feet, $inches)));
    }

    public function testASplitRoundedFirstNeverShowsTwelveInches(): void
    {
        $height = $this->registry->measurement('179.9', 'cm');
        $units = [$this->registry->unit('[ft_i]'), $this->registry->unit('[in_i]')];

        self::assertSame(['5 [ft_i]', '11 [in_i]'], array_map(strval(...), $height->splitRounded($units, 0)));
        self::assertSame(['6 [ft_i]', '0 [in_i]'], array_map(strval(...), $this->registry->measurement('182.8', 'cm')->splitRounded($units, 0)));
        self::assertSame(['5 [ft_i]', '11 [in_i]'], array_map(strval(...), $this->registry->measurement('182.8', 'cm')->splitRounded($units, 0, RoundingMode::TowardsZero)));
        self::assertSame(['1 [stone_av]', '2 [lb_av]', '8 [oz_av]'], array_map(strval(...), $this->registry->measurement('16.5', '[lb_av]')->split($this->registry->unit('[stone_av]'), $this->registry->unit('[lb_av]'), $this->registry->unit('[oz_av]'))));
    }

    public function testATemperatureIsWrittenInOneUnitOnly(): void
    {
        self::assertSame(['-4 [degF]'], array_map(strval(...), $this->registry->measurement('-20', 'Cel')->splitRounded([$this->registry->unit('[degF]')], 0)));

        $this->expectException(IncompatibleUnits::class);
        $this->registry->measurement('-20', 'Cel')->split($this->registry->unit('K'), $this->registry->unit('Cel'));
    }
}
