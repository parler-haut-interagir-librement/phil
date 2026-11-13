<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit;

use Phil\PrintPreflight\Size;
use Phil\PrintPreflightBundle\Application\Exception\InvalidMeasurement;
use Phil\PrintPreflightBundle\Application\Lengths;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lengths::class)]
#[CoversClass(InvalidMeasurement::class)]
final class LengthsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, float}>
     */
    public static function lengths(): iterable
    {
        yield 'millimetres' => ['3mm', 3 * 72 / 25.4];
        yield 'inches with spaces' => [' 0.125 in ', 9.0];
        yield 'points' => ['9pt', 9.0];
        yield 'pixels' => ['96px', 72.0];
        yield 'upper case' => ['1IN', 72.0];
        yield 'leading dot' => ['.5in', 36.0];
        yield 'zero' => ['0', 0.0];
        yield 'zero with decimals' => ['0.0', 0.0];
    }

    #[DataProvider('lengths')]
    public function testReadsALength(string $text, float $points): void
    {
        self::assertEqualsWithDelta($points, Lengths::length($text)->points, 0.0001);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badLengths(): iterable
    {
        yield 'unknown unit' => ['3cm', 'A length is a number and a unit, mm, in, pt or px, such as "3mm"; "3cm" is not.'];
        yield 'negative' => ['-3mm', 'A length is a number and a unit, mm, in, pt or px, such as "3mm"; "-3mm" is not.'];
        yield 'text before' => ['x3mm', 'A length is a number and a unit, mm, in, pt or px, such as "3mm"; "x3mm" is not.'];
        yield 'text after' => ['3mmx', 'A length is a number and a unit, mm, in, pt or px, such as "3mm"; "3mmx" is not.'];
        yield 'no unit' => ['3', 'A length other than 0 has a unit, mm, in, pt or px; "3" has none.'];
    }

    #[DataProvider('badLengths')]
    public function testRefusesALength(string $text, string $message): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains($message);

        Lengths::length($text);
    }

    public function testReadsASize(): void
    {
        self::assertTrue(Lengths::size('A4')->matches(Size::a4()));
        self::assertTrue(Lengths::size(' a5 ')->matches(Size::a5()));
        self::assertTrue(Lengths::size('letter')->matches(Size::letter()));
        self::assertTrue(Lengths::size('148x210mm')->matches(Size::a5()));
        self::assertTrue(Lengths::size(' 8.5 X 11 IN ')->matches(Size::letter()));
        self::assertTrue(Lengths::size('612x792pt')->matches(Size::letter()));
        self::assertTrue(Lengths::size('816x1056px')->matches(Size::letter()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badSizes(): iterable
    {
        yield 'no unit' => ['148x210'];
        yield 'one number' => ['148mm'];
        yield 'text before' => ['b148x210mm'];
        yield 'text after' => ['148x210mm!'];
        yield 'unknown name' => ['a3'];
    }

    #[DataProvider('badSizes')]
    public function testRefusesASize(string $text): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A size is a4, a5, letter, or a width and a height with a unit, such as "148x210mm"; "%s" is not.', $text));

        Lengths::size($text);
    }

    public function testALengthInAUnit(): void
    {
        self::assertSame(72.0, Lengths::in(1, 'in')->points);
        self::assertSame(12.0, Lengths::in(12, 'pt')->points);
        self::assertSame(9.0, Lengths::in(12, 'px')->points);
        self::assertEqualsWithDelta(72.0, Lengths::in(25.4, 'mm')->points, 0.0001);

        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains('The units are mm, in, pt and px; "cm" is not one.');

        Lengths::in(1, 'cm');
    }
}
