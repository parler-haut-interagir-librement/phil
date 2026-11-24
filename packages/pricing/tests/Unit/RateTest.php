<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\Rate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rate::class)]
#[CoversClass(InvalidRate::class)]
final class RateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function percents(): iterable
    {
        yield 'whole' => ['35', 350_000, '35'];
        yield 'three decimals' => ['9.975', 99_750, '9.975'];
        yield 'four decimals' => ['0.0001', 1, '0.0001'];
        yield 'trailing zeros dropped' => ['12.5000', 125_000, '12.5'];
        yield 'negative' => ['-12.5', -125_000, '-12.5'];
        yield 'negative under one percent' => ['-0.25', -2_500, '-0.25'];
        yield 'above one hundred' => ['150', 1_500_000, '150'];
        yield 'zero' => ['0', 0, '0'];
        yield 'negative zero' => ['-0', 0, '0'];
        yield 'twelve digits' => ['999999999999.9999', 9_999_999_999_999_999, '999999999999.9999'];
    }

    #[DataProvider('percents')]
    public function testAPercentIsReadAndPrintedBack(string $percent, int $partsPerMillion, string $printed): void
    {
        $rate = Rate::ofPercent($percent);

        self::assertSame($partsPerMillion, $rate->partsPerMillion);
        self::assertSame($printed, $rate->percent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notPercents(): iterable
    {
        yield 'empty' => [''];
        yield 'five decimals' => ['1.00001'];
        yield 'a sign of plus' => ['+5'];
        yield 'a percent sign' => ['5%'];
        yield 'a comma' => ['5,5'];
        yield 'a dot alone' => ['5.'];
        yield 'thirteen digits' => ['1000000000000'];
        yield 'a space' => [' 5'];
        yield 'a trailing line break' => ["5\n"];
        yield 'leading text' => ['x5'];
    }

    #[DataProvider('notPercents')]
    public function testAMalformedPercentIsRefused(string $percent): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs(\sprintf('"%s" is not a percentage: digits, with an optional "-" and up to four decimals, are expected.', $percent));

        Rate::ofPercent($percent);
    }

    public function testARateIsKeptAsGiven(): void
    {
        self::assertSame(-2_000_000, Rate::of(-2_000_000)->partsPerMillion);
        self::assertSame(\PHP_INT_MAX, Rate::of(\PHP_INT_MAX)->partsPerMillion);
        self::assertSame('-200', Rate::of(-2_000_000)->percent());
        self::assertSame('0.0012', Rate::of(12)->percent());
    }

    public function testTheSmallestIntegerIsRefused(): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs('The rate leaves the range of a native integer.');

        Rate::of(\PHP_INT_MIN);
    }

    public function testBasisPointsAreHundredthsOfAPercent(): void
    {
        self::assertSame(12_500, Rate::ofBasisPoints(125)->partsPerMillion);
        self::assertSame(-100, Rate::ofBasisPoints(-1)->partsPerMillion);
    }

    public function testBasisPointsOutOfRangeAreRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        Rate::ofBasisPoints(\PHP_INT_MAX);
    }

    public function testZeroIsZero(): void
    {
        self::assertSame(0, Rate::zero()->partsPerMillion);
        self::assertFalse(Rate::zero()->isNegative());
    }

    public function testTheSignIsTold(): void
    {
        self::assertTrue(Rate::of(-1)->isNegative());
        self::assertFalse(Rate::of(1)->isNegative());
    }

    public function testRatesCompare(): void
    {
        self::assertTrue(Rate::ofPercent('40')->equals(Rate::of(400_000)));
        self::assertFalse(Rate::ofPercent('40')->equals(Rate::of(400_001)));
        self::assertSame(-1, Rate::of(1)->compare(Rate::of(2)));
        self::assertSame(0, Rate::of(2)->compare(Rate::of(2)));
        self::assertSame(1, Rate::of(3)->compare(Rate::of(2)));
    }
}
