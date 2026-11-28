<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Unit;

use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Exception\InvalidUnitDefinition;
use Phil\Measurement\Exception\UnknownUnit;
use Phil\Measurement\Fraction;
use Phil\Measurement\Measurement;
use Phil\Measurement\Unit;
use Phil\Measurement\UnitFamily;
use Phil\Measurement\UnitRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnitRegistry::class)]
#[CoversClass(Unit::class)]
#[CoversClass(UnitFamily::class)]
#[CoversClass(UnknownUnit::class)]
#[CoversClass(InvalidUnitDefinition::class)]
#[CoversClass(InvalidMeasurement::class)]
#[CoversClass(Measurement::class)]
#[CoversClass(Fraction::class)]
final class UnitRegistryTest extends TestCase
{
    public function testTheStandardRegistryHoldsTheDeclaredFamilies(): void
    {
        $registry = UnitRegistry::standard();

        self::assertSame(
            ['area', 'duration', 'energy', 'fuel-consumption', 'length', 'mass', 'power', 'pressure', 'speed', 'temperature', 'temperature-difference', 'volume'],
            array_keys($registry->families()),
        );
        self::assertSame('kg', $registry->family('mass')->standard->code);
        self::assertSame('kg', array_key_first($registry->family('mass')->units));
        self::assertSame('mass', $registry->family('mass')->cldr);
        self::assertSame('temperature-difference', $registry->family('temperature')->difference);
        self::assertNull($registry->family('mass')->difference);
        self::assertTrue($registry->family('mass')->has('[lb_av]'));
        self::assertFalse($registry->family('mass')->has('m'));
        self::assertSame('mass-pound', $registry->family('mass')->unitForCldr('mass-pound')?->cldr);
        self::assertNull($registry->family('mass')->unitForCldr('mass-grain'));
    }

    public function testItDescribesEachUnit(): void
    {
        $registry = UnitRegistry::standard();
        $pound = $registry->unit('[lb_av]');
        $fahrenheit = $registry->unit('[degF]');
        $milesPerGallon = $registry->unit('[mi_i]/[gal_us]');

        self::assertSame(['[lb_av]', 'mass', '[lb_av]', 'mass-pound'], [$pound->code, $pound->family, $pound->ucum, $pound->cldr]);
        self::assertSame('0.45359237', $pound->factor());
        self::assertSame('0', $pound->offset());
        self::assertTrue($pound->additive);
        self::assertFalse($pound->reciprocal);
        self::assertFalse($pound->isStandard());
        self::assertTrue($registry->unit('kg')->isStandard());
        self::assertNull($pound->difference);
        self::assertSame('5/9', $fahrenheit->factor());
        self::assertSame('45967/180', $fahrenheit->offset());
        self::assertSame('255.372222222222222', Fraction::parse($fahrenheit->offset())->toCanonicalDecimal());
        self::assertFalse($fahrenheit->additive);
        self::assertSame('[degF]{difference}', $fahrenheit->difference?->code);
        self::assertSame('[degF]', $fahrenheit->difference->ucum);
        self::assertTrue($milesPerGallon->reciprocal);
        self::assertFalse($milesPerGallon->isStandard());
        self::assertFalse($milesPerGallon->additive);
        self::assertSame('Cel', $registry->unit('Cel{difference}')->ucum);
        self::assertTrue($registry->unit('Cel{difference}')->additive);
    }

    public function testAReciprocalUnitWithAFactorOfOneIsNotStandard(): void
    {
        $registry = UnitRegistry::fromDefinitions(['rate' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1'], 'b' => ['factor' => '1', 'reciprocal' => true]]]]);

        self::assertFalse($registry->unit('b')->isStandard());
        self::assertFalse($registry->unit('b')->additive);
    }

    public function testItFindsUnitsByCodeAndByUcumCode(): void
    {
        $registry = UnitRegistry::standard();

        self::assertTrue($registry->has('kg'));
        self::assertFalse($registry->has('KG'));
        self::assertSame('[lb_av]', $registry->fromUcum('[lb_av]')->code);
        self::assertSame('Cel', $registry->fromUcum('Cel', 'temperature')->code);
        self::assertSame('Cel{difference}', $registry->fromUcum('Cel', 'temperature-difference')->code);
    }

    public function testAUcumCodeOfTwoFamiliesNeedsTheFamily(): void
    {
        $this->expectException(UnknownUnit::class);
        $this->expectExceptionMessageIsOrContains('names a unit of more than one family');
        UnitRegistry::standard()->fromUcum('Cel');
    }

    public function testAnUnknownUcumCodeIsRefused(): void
    {
        $this->expectException(UnknownUnit::class);
        $this->expectExceptionMessageIsOrContains('No unit of the registry has the UCUM code "kg"');
        UnitRegistry::standard()->fromUcum('kg', 'length');
    }

    public function testAnUnknownUnitOrFamilyIsRefused(): void
    {
        try {
            UnitRegistry::standard()->unit('furlong');
            self::fail('An unknown unit is refused.');
        } catch (UnknownUnit $unknownUnit) {
            self::assertSame('The unit "furlong" is not in the registry.', $unknownUnit->getMessage());
        }

        $this->expectException(UnknownUnit::class);
        $this->expectExceptionMessageIsOrContains('The family "luminosity" is not in the registry.');
        UnitRegistry::standard()->family('luminosity');
    }

    public function testItParsesAMeasurement(): void
    {
        $registry = UnitRegistry::standard();

        self::assertSame('12.5 kg', $registry->parse('12.5 kg')->toString());
        self::assertSame('-40 Cel', $registry->measurement('-40', 'Cel')->toString());
        self::assertSame('1 L/(100.km)', $registry->parse('1 L/(100.km)')->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notMeasurements(): iterable
    {
        yield 'no space' => ['12kg'];
        yield 'leading space' => [' 12 kg'];
        yield 'too long' => ['1 '.str_repeat('k', 199)];
    }

    #[DataProvider('notMeasurements')]
    public function testItRefusesWhatIsNotAMeasurement(string $text): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains('is not a measurement');
        UnitRegistry::standard()->parse($text);
    }

    public function testATextOfTwoHundredBytesIsRead(): void
    {
        $this->expectException(UnknownUnit::class);
        UnitRegistry::standard()->parse('1 '.str_repeat('k', 198));
    }

    public function testDefinitionsAddUnitsAndFamilies(): void
    {
        $registry = UnitRegistry::standard()->withDefinitions([
            'mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891', 'cldr' => 'mass-grain']]],
            'paper-weight' => ['standard' => 'g/m2', 'units' => ['g/m2' => ['factor' => '1'], '[lb_av]/[ream]' => ['factor' => '1.48', 'ucum' => '[lb_av]']]],
        ]);

        self::assertSame('mass', $registry->unit('[gr]')->family);
        self::assertSame('mass-grain', $registry->unit('[gr]')->cldr);
        self::assertSame('[lb_av]', $registry->unit('[lb_av]/[ream]')->ucum);
        self::assertNull($registry->family('paper-weight')->cldr);
        self::assertSame('7000', $registry->measurement('1', '[lb_av]')->convertTo($registry->unit('[gr]'))->toDecimal(6));
        self::assertArrayHasKey('[gr]', $registry->definitions()['mass']['units']);
        self::assertFalse(UnitRegistry::standard()->has('[gr]'));
    }

    public function testAddedUnitsKeepTheFamilySettings(): void
    {
        $registry = UnitRegistry::standard()->withDefinitions(['mass' => ['standard' => 'kg', 'cldr' => 'mass', 'units' => ['[gr]' => ['factor' => '0.00006479891']]]]);

        self::assertSame('kg', $registry->family('mass')->standard->code);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidDefinitions(): iterable
    {
        $unit = ['a' => ['factor' => '1']];

        yield 'family name' => [['Mass' => ['standard' => 'a', 'units' => $unit]], 'A family is named by lowercase letters'];
        yield 'numeric family name' => [[0 => ['standard' => 'a', 'units' => $unit]], 'A family is named by lowercase letters'];
        yield 'long family name' => [[str_repeat('a', 65) => ['standard' => 'a', 'units' => $unit]], 'A family is named by lowercase letters'];
        yield 'family name with a line break' => [["mass\n" => ['standard' => 'a', 'units' => $unit]], 'A family is named by lowercase letters'];
        yield 'family not an array' => [['x' => 'a'], 'The family "x" is defined by standard, units, cldr, difference only.'];
        yield 'unknown family key' => [['x' => ['standard' => 'a', 'units' => $unit, 'symbol' => 'a']], 'The family "x" is defined by'];
        yield 'no units' => [['x' => ['standard' => 'a']], 'The family "x" defines one unit or more under "units".'];
        yield 'empty units' => [['x' => ['standard' => 'a', 'units' => []]], 'The family "x" defines one unit or more'];
        yield 'standard not a string' => [['x' => ['standard' => 1, 'units' => $unit]], 'The "standard" of the family "x" is a string.'];
        yield 'no standard' => [['x' => ['units' => $unit]], 'The family "x" names its standard unit, one of its units.'];
        yield 'standard not a unit' => [['x' => ['standard' => 'b', 'units' => $unit]], 'The family "x" names its standard unit'];
        yield 'standard with a factor' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '2']]]], 'The standard unit "a" has a factor of 1'];
        yield 'standard with an offset' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1', 'offset' => '1']]]], 'The standard unit "a" has a factor of 1'];
        yield 'unit code with a space' => [['x' => ['standard' => 'a', 'units' => ['a b' => ['factor' => '1']]]], 'A unit code of the family "x" is 1 to 64 printable ASCII characters'];
        yield 'numeric unit code' => [['x' => ['standard' => 'a', 'units' => [5 => ['factor' => '1']]]], 'A unit code of the family "x"'];
        yield 'long unit code' => [['x' => ['standard' => 'a', 'units' => [str_repeat('a', 65) => ['factor' => '1']]]], 'A unit code of the family "x"'];
        yield 'unit not an array' => [['x' => ['standard' => 'a', 'units' => ['a' => '1']]], 'The unit "a" is defined by factor, offset, reciprocal, ucum, cldr, difference only.'];
        yield 'unknown unit key' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1', 'symbol' => 'a']]]], 'The unit "a" is defined by'];
        yield 'no factor' => [['x' => ['standard' => 'a', 'units' => ['a' => []]]], 'The factor of the unit "a" is a string'];
        yield 'float factor' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => 1.0]]]], 'The factor of the unit "a" is a string'];
        yield 'factor not a number' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => 'one']]]], 'The factor of the unit "a" is not an exact number: "one" is not a decimal'];
        yield 'zero factor' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1'], 'b' => ['factor' => '0']]]], 'The factor of the unit "b" is above zero.'];
        yield 'negative factor' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1'], 'b' => ['factor' => '-2']]]], 'The factor of the unit "b" is above zero.'];
        yield 'offset not a number' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1', 'offset' => 'x']]]], 'The offset of the unit "a" is not an exact number'];
        yield 'reciprocal not a boolean' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1'], 'b' => ['factor' => '1', 'reciprocal' => 'yes']]]], '"reciprocal" is true or false'];
        yield 'reciprocal with an offset' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1'], 'b' => ['factor' => '1', 'offset' => '1', 'reciprocal' => true]]]], 'a reciprocal unit, "b", has no offset.'];
        yield 'ucum not a string' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1', 'ucum' => 1]]]], 'The "ucum" of the unit "a" is 1 to 64 printable ASCII characters.'];
        yield 'cldr with a space' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1', 'cldr' => 'a b']]]], 'The "cldr" of the unit "a"'];
        yield 'difference too long' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1', 'difference' => str_repeat('a', 65)]]]], 'The "difference" of the unit "a"'];
        yield 'difference without a family of differences' => [['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1', 'difference' => 'a']]]], 'The unit "a" names a unit of differences, but its family has no "difference" family.'];
        yield 'unknown family of differences' => [['x' => ['standard' => 'a', 'difference' => 'y', 'units' => $unit]], 'The family "x" measures its differences in "y"'];
        yield 'family of differences with differences' => [[
            'x' => ['standard' => 'a', 'difference' => 'y', 'units' => ['a' => ['factor' => '1', 'difference' => 'b']]],
            'y' => ['standard' => 'b', 'difference' => 'z', 'units' => ['b' => ['factor' => '1', 'difference' => 'c']]],
            'z' => ['standard' => 'c', 'units' => ['c' => ['factor' => '1']]],
        ], 'The family "x" measures its differences in "y", a family of the registry without differences of its own.'];
        yield 'unit without its unit of differences' => [[
            'x' => ['standard' => 'a', 'difference' => 'y', 'units' => ['a' => ['factor' => '1']]],
            'y' => ['standard' => 'b', 'units' => ['b' => ['factor' => '1']]],
        ], 'The unit "a" names its unit of differences, a unit of the family "y" with the same factor.'];
        yield 'unit of differences with another factor' => [[
            'x' => ['standard' => 'a', 'difference' => 'y', 'units' => ['a' => ['factor' => '1', 'difference' => 'c']]],
            'y' => ['standard' => 'b', 'units' => ['b' => ['factor' => '1'], 'c' => ['factor' => '2']]],
        ], 'The unit "a" names its unit of differences'];
        yield 'code of another family' => [[
            'x' => ['standard' => 'a', 'units' => $unit],
            'y' => ['standard' => 'a', 'units' => $unit],
        ], 'The unit "a" is already defined in the family "x".'];
    }

    /**
     * @param array<mixed> $definitions
     */
    #[DataProvider('invalidDefinitions')]
    public function testItRefusesInvalidDefinitions(array $definitions, string $message): void
    {
        $this->expectException(InvalidUnitDefinition::class);
        $this->expectExceptionMessageIsOrContains($message);
        UnitRegistry::fromDefinitions($definitions);
    }

    public function testAddedDefinitionsCannotChangeAFamilyOrRedefineAUnit(): void
    {
        try {
            UnitRegistry::standard()->withDefinitions(['mass' => ['standard' => 'g', 'units' => ['[gr]' => ['factor' => '0.00006479891']]]]);
            self::fail('The standard unit of a family is kept.');
        } catch (InvalidUnitDefinition $invalidUnitDefinition) {
            self::assertSame('The family "mass" already has its "standard": added units cannot change it.', $invalidUnitDefinition->getMessage());
        }

        try {
            UnitRegistry::standard()->withDefinitions(['mass' => ['cldr' => null, 'units' => ['[gr]' => ['factor' => '0.00006479891']]]]);
            self::fail('The category of a family is kept.');
        } catch (InvalidUnitDefinition $invalidUnitDefinition) {
            self::assertStringContainsString('already has its "cldr"', $invalidUnitDefinition->getMessage());
        }

        $this->expectException(InvalidUnitDefinition::class);
        $this->expectExceptionMessageIsOrContains('The unit "g" is already defined.');
        UnitRegistry::standard()->withDefinitions(['mass' => ['units' => ['g' => ['factor' => '0.001']]]]);
    }

    public function testANewFamilyMayLeaveItsOptionalSettingsNull(): void
    {
        $registry = UnitRegistry::fromDefinitions(['x' => ['standard' => 'a', 'cldr' => null, 'difference' => null, 'units' => ['a' => ['factor' => '1']]]]);

        self::assertNull($registry->family('x')->cldr);
        self::assertNull($registry->family('x')->difference);
    }

    public function testTheSizeOfARegistryIsBounded(): void
    {
        $families = [];

        for ($index = 0; $index < UnitRegistry::MAXIMUM_FAMILIES; ++$index) {
            $families['f'.$index] = ['standard' => 'u'.$index, 'units' => ['u'.$index => ['factor' => '1']]];
        }

        self::assertCount(UnitRegistry::MAXIMUM_FAMILIES, UnitRegistry::fromDefinitions($families)->families());

        try {
            UnitRegistry::fromDefinitions($families + ['extra' => ['standard' => 'x', 'units' => ['x' => ['factor' => '1']]]]);
            self::fail('A registry holds 64 families at most.');
        } catch (InvalidUnitDefinition $invalidUnitDefinition) {
            self::assertSame('A registry holds 64 families at most.', $invalidUnitDefinition->getMessage());
        }

        $units = [];

        for ($index = 0; $index < UnitRegistry::MAXIMUM_UNITS; ++$index) {
            $units['u'.$index] = ['factor' => '1'];
        }

        self::assertCount(UnitRegistry::MAXIMUM_UNITS, UnitRegistry::fromDefinitions(['x' => ['standard' => 'u0', 'units' => $units]])->family('x')->units);

        $this->expectException(InvalidUnitDefinition::class);
        $this->expectExceptionMessageIsOrContains('A registry holds 1024 units at most.');
        UnitRegistry::fromDefinitions(['x' => ['standard' => 'u0', 'units' => $units + ['more' => ['factor' => '1']]]]);
    }

    public function testAFamilyOfDifferencesMayBeDeclaredAfterTheFamilyThatUsesIt(): void
    {
        $registry = UnitRegistry::fromDefinitions([
            'level' => ['standard' => 'a', 'difference' => 'level-change', 'units' => ['a' => ['factor' => '1', 'difference' => 'd']]],
            'level-change' => ['standard' => 'd', 'units' => ['d' => ['factor' => '1']]],
        ]);

        self::assertSame('d', $registry->unit('a')->difference?->code);
        self::assertSame(['level', 'level-change'], array_keys($registry->families()));
    }

    public function testAUnitCodeEndingInANewlineIsRefused(): void
    {
        $this->expectException(InvalidUnitDefinition::class);
        UnitRegistry::fromDefinitions(['x' => ['standard' => "a\n", 'units' => ["a\n" => ['factor' => '1']]]]);
    }

    public function testAUcumCodeEndingInANewlineIsRefused(): void
    {
        $this->expectException(InvalidUnitDefinition::class);
        UnitRegistry::fromDefinitions(['x' => ['standard' => 'a', 'units' => ['a' => ['factor' => '1', 'ucum' => "a\n"]]]]);
    }

    public function testTheFamiliesOfDifferencesComeFirstAndTheRestKeepsItsOrder(): void
    {
        $registry = UnitRegistry::fromDefinitions([
            'first-affine' => ['standard' => 'a1', 'difference' => 'shift', 'units' => ['a1' => ['factor' => '1', 'difference' => 'd']]],
            'plain-b' => ['standard' => 'p1', 'units' => ['p1' => ['factor' => '1']]],
            'shift' => ['standard' => 'd', 'units' => ['d' => ['factor' => '1']]],
            'second-affine' => ['standard' => 'a2', 'difference' => 'shift', 'units' => ['a2' => ['factor' => '1', 'difference' => 'd']]],
            'plain-a' => ['standard' => 'q1', 'units' => ['q1' => ['factor' => '1']]],
            'plain-c' => ['standard' => 'r1', 'units' => ['r1' => ['factor' => '1']]],
            'third-affine' => ['standard' => 'a3', 'difference' => 'shift', 'units' => ['a3' => ['factor' => '1', 'difference' => 'd']]],
        ]);

        self::assertSame(['plain-b', 'shift', 'plain-a', 'plain-c', 'first-affine', 'second-affine', 'third-affine'], array_keys($registry->definitions()));
    }

    public function testAManyFamiliesOfDifferencesAreSortedWithoutBeingShuffled(): void
    {
        $definitions = ['shift' => ['standard' => 'd', 'units' => ['d' => ['factor' => '1']]]];
        $plain = ['shift'];
        $affine = [];

        for ($index = 0; 20 > $index; ++$index) {
            if (1 === $index % 3) {
                $definitions['f'.$index] = ['standard' => 'a'.$index, 'difference' => 'shift', 'units' => ['a'.$index => ['factor' => '1', 'difference' => 'd']]];
                $affine[] = 'f'.$index;

                continue;
            }

            $definitions['f'.$index] = ['standard' => 'p'.$index, 'units' => ['p'.$index => ['factor' => '1']]];
            $plain[] = 'f'.$index;
        }

        self::assertSame([...$plain, ...$affine], array_keys(UnitRegistry::fromDefinitions($definitions)->definitions()));
    }

    public function testTheStandardUnitComesFirstInItsFamily(): void
    {
        $registry = UnitRegistry::fromDefinitions(['rate' => ['standard' => 'b', 'units' => ['a' => ['factor' => '2'], 'b' => ['factor' => '1'], 'c' => ['factor' => '3']]]]);

        self::assertSame(['b', 'a', 'c'], array_keys($registry->family('rate')->units));
    }
}
