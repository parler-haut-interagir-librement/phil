<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Unit\Cldr;

use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitWidth;
use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\UnitRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RoundingMode;

#[CoversClass(UnitFormatter::class)]
final class UnitFormatterTest extends TestCase
{
    private UnitRegistry $registry;

    private UnitFormatter $formatter;

    protected function setUp(): void
    {
        $this->registry = UnitRegistry::standard();
        $this->formatter = UnitFormatter::cldr();
    }

    /**
     * @return iterable<string, array{string, string, string, UnitWidth, string}>
     */
    public static function formats(): iterable
    {
        yield 'French long' => ['1234.5', 'kg', 'fr_CA', UnitWidth::Long, "1\u{a0}234,5\u{a0}kilogrammes"];
        yield 'French singular' => ['1', 'kg', 'fr', UnitWidth::Long, "1\u{a0}kilogramme"];
        yield 'French one and a half is singular' => ['1.5', 'kg', 'fr', UnitWidth::Long, "1,5\u{a0}kilogramme"];
        yield 'English plural of one and a half' => ['1.5', 'kg', 'en', UnitWidth::Long, '1.5 kilograms'];
        yield 'English short' => ['-1234.5', 'kg', 'en', UnitWidth::Short, '-1,234.5 kg'];
        yield 'English narrow' => ['12', 'kg', 'en', UnitWidth::Narrow, '12kg'];
        yield 'BCP 47 locale' => ['1', '[lb_av]', 'en-US', UnitWidth::Long, '1 pound'];
        yield 'German' => ['1234.5', 'kg', 'de', UnitWidth::Short, '1.234,5 kg'];
        yield 'Polish few' => ['3', 'kg', 'pl', UnitWidth::Long, '3 kilogramy'];
        yield 'Polish many' => ['5', 'kg', 'pl', UnitWidth::Long, '5 kilogramów'];
        yield 'parent locale' => ['2', 'kg', 'fr_BE', UnitWidth::Long, "2\u{a0}kilogrammes"];
        yield 'fallback locale' => ['2', 'kg', 'xx', UnitWidth::Long, '2 kilograms'];
        yield 'difference of temperature' => ['2', 'Cel{difference}', 'en', UnitWidth::Long, '2 degrees Celsius'];
        yield 'unit without patterns' => ['12', 'W.h', 'fr', UnitWidth::Long, '12 W.h'];
        yield 'zero' => ['0', 'kg', 'en', UnitWidth::Long, '0 kilograms'];
        yield 'negative below one' => ['-0.5', 'kg', 'de', UnitWidth::Short, '-0,5 kg'];
    }

    #[DataProvider('formats')]
    public function testItWritesTheMeasurementInTheLocale(string $value, string $unit, string $locale, UnitWidth $width, string $expected): void
    {
        self::assertSame($expected, $this->formatter->format($this->registry->measurement($value, $unit), $locale, $width));
    }

    public function testTheDigitsAreRoundedWithoutAFloat(): void
    {
        $value = $this->registry->measurement('123456789012345.678', 'g');

        self::assertSame('123,456,789,012,345.68 g', $this->formatter->format($value, 'en'));
        self::assertSame('123,456,789,012,345.678 g', $this->formatter->format($value, 'en', maximumFractionDigits: 18));
        self::assertSame('123,456,789,012,346 g', $this->formatter->format($value, 'en', maximumFractionDigits: 0));
        self::assertSame('123,456,789,012,345.6 g', $this->formatter->format($value, 'en', maximumFractionDigits: 1, mode: RoundingMode::TowardsZero));
    }

    public function testTheLocalesDigitsAreUsed(): void
    {
        self::assertSame('١٫٥ كغم', $this->formatter->format($this->registry->measurement('1.5', 'kg'), 'ar_EG'));
    }

    public function testFractionDigitsAreBounded(): void
    {
        try {
            $this->formatter->format($this->registry->measurement('1', 'kg'), 'en', maximumFractionDigits: -1);
            self::fail('Negative fraction digits are refused.');
        } catch (InvalidMeasurement $invalidMeasurement) {
            self::assertSame('A measurement is written with 0 to 18 fraction digits, -1 asked.', $invalidMeasurement->getMessage());
        }

        $this->expectException(InvalidMeasurement::class);
        $this->formatter->format($this->registry->measurement('1', 'kg'), 'en', maximumFractionDigits: 19);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLocales(): iterable
    {
        yield 'path' => ['../en'];
        yield 'uppercase language' => ['EN'];
        yield 'line break' => ["en\n"];
        yield 'too many subtags' => ['en_aa_bb_cc_dd'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidLocales')]
    public function testItRefusesAnInvalidLocale(string $locale): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains('is not a locale such as "fr_CA"');
        $this->formatter->format($this->registry->measurement('1', 'kg'), $locale);
    }

    public function testALocaleOfFourSubtagsIsAccepted(): void
    {
        self::assertSame('1 kg', $this->formatter->format($this->registry->measurement('1', 'kg'), 'en_Latn_US_posix'));
    }

    public function testPartsAreJoinedAsTheLocaleJoinsUnits(): void
    {
        $parts = [$this->registry->measurement('5', '[ft_i]'), $this->registry->measurement('10', '[in_i]')];

        self::assertSame('5 ft, 10 in', $this->formatter->formatParts($parts, 'en'));
        self::assertSame('5 feet, 10 inches', $this->formatter->formatParts($parts, 'en', UnitWidth::Long));
        self::assertSame('5′ 10″', $this->formatter->formatParts($parts, 'en', UnitWidth::Narrow));
        self::assertSame('5 pieds et 10 pouces', str_replace("\u{a0}", ' ', $this->formatter->formatParts($parts, 'fr_CA', UnitWidth::Long)));
        self::assertSame('5 ft', $this->formatter->formatParts([$parts[0]], 'en'));
    }

    public function testItNamesAUnit(): void
    {
        self::assertSame('kilogrammes', $this->formatter->unitName('mass-kilogram', 'fr'));
        self::assertSame('kg', $this->formatter->unitName('mass-kilogram', 'fr', UnitWidth::Short));
        self::assertNull($this->formatter->unitName(null, 'fr'));
        self::assertNull($this->formatter->unitName('mass-nothing', 'fr'));
    }

    public function testAnotherDirectoryOfPatternsIsRead(): void
    {
        $formatter = new UnitFormatter(__DIR__.'/missing');

        self::assertSame('2 kg', $formatter->format($this->registry->measurement('2', 'kg'), 'en'));
    }

    public function testTheKeptLocalesAreBounded(): void
    {
        $value = $this->registry->measurement('2', 'kg');

        for ($index = 0; $index <= UnitFormatter::KEPT; ++$index) {
            $this->formatter->format($value, 'en_'.str_pad((string) $index, 3, '0', \STR_PAD_LEFT));

            self::assertLessThanOrEqual(UnitFormatter::KEPT, \count($this->kept($this->formatter, 'patterns')));
            self::assertLessThanOrEqual(UnitFormatter::KEPT, \count($this->kept($this->formatter, 'locales')));
        }

        self::assertSame("2\u{202f}kg", $this->formatter->format($value, 'fr'));
        self::assertSame('2 kg', str_replace("\u{a0}", ' ', $this->formatter->format($value, 'en_001')));
    }

    public function testTheLocalesBelowTheBoundAreAllKept(): void
    {
        $value = $this->registry->measurement('2', 'kg');

        for ($index = 0; $index < 10; ++$index) {
            $this->formatter->format($value, 'en_'.str_pad((string) $index, 3, '0', \STR_PAD_LEFT));
        }

        self::assertCount(10, $this->kept($this->formatter, 'locales'));
        self::assertCount(11, $this->kept($this->formatter, 'patterns'));
    }

    public function testAnInvalidLocaleIsShownCutAtThirtyTwoBytes(): void
    {
        $long = 'X'.str_repeat('a', 39);

        try {
            $this->formatter->format($this->registry->measurement('1', 'kg'), $long);
            self::fail('A locale of uppercase letters is refused.');
        } catch (InvalidMeasurement $invalidMeasurement) {
            self::assertSame(\sprintf('"%s" is not a locale such as "fr_CA".', 'X'.str_repeat('a', 31)), $invalidMeasurement->getMessage());
        }

        try {
            $this->formatter->format($this->registry->measurement('1', 'kg'), str_repeat('a', 31).'étail');
            self::fail('An accent is refused.');
        } catch (InvalidMeasurement $invalidMeasurement) {
            self::assertSame(\sprintf('"%s" is not a locale such as "fr_CA".', str_repeat('a', 31)), $invalidMeasurement->getMessage());
        }
    }

    public function testThePartsKeepTwoFractionDigitsByDefault(): void
    {
        $parts = [$this->registry->measurement('5', '[ft_i]'), $this->registry->measurement('10.567', '[in_i]')];

        self::assertSame('5 ft, 10.57 in', $this->formatter->formatParts($parts, 'en'));
        self::assertSame('10.57 in', $this->formatter->formatParts([$parts[1]], 'en'));
    }

    public function testANegativeNumberTakesThePrefixAndTheSuffixOfItsLocale(): void
    {
        $symbols = new ReflectionMethod($this->formatter, 'symbols')->invoke($this->formatter, 'en');
        self::assertIsArray($symbols);
        $symbols['prefix'] = '(';
        $symbols['suffix'] = ')';

        self::assertSame('(1,234.5)', new ReflectionMethod($this->formatter, 'number')->invoke($this->formatter, '-1234.5', $symbols));
        self::assertSame('1,234.5', new ReflectionMethod($this->formatter, 'number')->invoke($this->formatter, '1234.5', $symbols));
    }

    /**
     * @return array<string, mixed>
     */
    private function kept(UnitFormatter $formatter, string $property): array
    {
        $kept = new ReflectionProperty($formatter, $property)->getValue($formatter);
        self::assertIsArray($kept);

        /** @var array<string, mixed> $kept */
        return $kept;
    }
}
