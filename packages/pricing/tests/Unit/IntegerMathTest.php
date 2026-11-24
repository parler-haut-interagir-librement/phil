<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\IntegerMath;
use Phil\Pricing\Rate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RoundingMode;

#[CoversClass(IntegerMath::class)]
#[CoversClass(AmountOutOfRange::class)]
final class IntegerMathTest extends TestCase
{
    /**
     * Each mode on 7/2 (an exact half), 5/2 (an exact half on an even quotient), 7/3 (under the half) and 8/3 (over
     * the half), on both signs, with the expected quotients in that order: 3.5, 2.5, 2.33, 2.67, then negated.
     *
     * @return iterable<string, array{RoundingMode, list<int>}>
     */
    public static function modes(): iterable
    {
        yield 'half away from zero' => [RoundingMode::HalfAwayFromZero, [4, 3, 2, 3, -4, -3, -2, -3]];
        yield 'half towards zero' => [RoundingMode::HalfTowardsZero, [3, 2, 2, 3, -3, -2, -2, -3]];
        yield 'half even' => [RoundingMode::HalfEven, [4, 2, 2, 3, -4, -2, -2, -3]];
        yield 'half odd' => [RoundingMode::HalfOdd, [3, 3, 2, 3, -3, -3, -2, -3]];
        yield 'towards zero' => [RoundingMode::TowardsZero, [3, 2, 2, 2, -3, -2, -2, -2]];
        yield 'away from zero' => [RoundingMode::AwayFromZero, [4, 3, 3, 3, -4, -3, -3, -3]];
        yield 'positive infinity' => [RoundingMode::PositiveInfinity, [4, 3, 3, 3, -3, -2, -2, -2]];
        yield 'negative infinity' => [RoundingMode::NegativeInfinity, [3, 2, 2, 2, -4, -3, -3, -3]];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('modes')]
    public function testEachModeRoundsAsNamed(RoundingMode $mode, array $expected): void
    {
        $actual = [];

        foreach ([[7, 2], [5, 2], [7, 3], [8, 3], [-7, 2], [-5, 2], [-7, 3], [-8, 3]] as [$numerator, $denominator]) {
            $actual[] = IntegerMath::divide($numerator, $denominator, $mode);
        }

        self::assertSame($expected, $actual);
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('modes')]
    public function testANegativeDenominatorRoundsAsTheNegatedNumerator(RoundingMode $mode, array $expected): void
    {
        foreach ([7, 5, 8, -7, -5, -8, 9] as $numerator) {
            self::assertSame(IntegerMath::divide(-$numerator, 3, $mode), IntegerMath::divide($numerator, -3, $mode));
            self::assertSame(IntegerMath::divide(-$numerator, 2, $mode), IntegerMath::divide($numerator, -2, $mode));
        }
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('modes')]
    public function testAnExactQuotientIsNeverRounded(RoundingMode $mode, array $expected): void
    {
        self::assertSame(3, IntegerMath::divide(9, 3, $mode));
        self::assertSame(-3, IntegerMath::divide(-9, 3, $mode));
        self::assertSame(0, IntegerMath::divide(0, 7, $mode));
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('modes')]
    public function testEveryModeAgreesWithTheNativeRoundingOfTheExactQuotient(RoundingMode $mode, array $expected): void
    {
        $random = new Randomizer(new Mt19937(20_261_120));

        for ($i = 0; $i < 2_000; ++$i) {
            $numerator = $random->getInt(-1_000_000, 1_000_000);
            $denominator = $random->getInt(1, 2_000);
            $expected = (int) round($numerator / $denominator, 0, $mode);

            self::assertSame($expected, IntegerMath::divide($numerator, $denominator, $mode), \sprintf('%d / %d', $numerator, $denominator));
        }
    }

    /**
     * A numerator of 1 or -1 is the smallest that leaves a remainder: its sign alone tells the side of the quotient.
     */
    public function testANumeratorOfOneTakesTheSideOfItsSign(): void
    {
        self::assertSame(1, IntegerMath::divide(1, 2, RoundingMode::AwayFromZero));
        self::assertSame(-1, IntegerMath::divide(1, -2, RoundingMode::AwayFromZero));
        self::assertSame(-1, IntegerMath::divide(-1, 2, RoundingMode::AwayFromZero));
        self::assertSame(1, IntegerMath::divide(-1, -2, RoundingMode::AwayFromZero));
    }

    public function testTheHalfIsFoundWithoutOverflowOnTheLargestDenominator(): void
    {
        $denominator = \PHP_INT_MAX - 1;
        $half = intdiv(\PHP_INT_MAX, 2);

        self::assertSame(1, IntegerMath::divide($half, $denominator, RoundingMode::HalfAwayFromZero));
        self::assertSame(0, IntegerMath::divide($half, $denominator, RoundingMode::HalfTowardsZero));
        self::assertSame(1, IntegerMath::divide($half + 1, $denominator, RoundingMode::HalfTowardsZero));
        self::assertSame(0, IntegerMath::divide($half - 1, $denominator, RoundingMode::HalfAwayFromZero));
    }

    public function testAProductInRangeIsExact(): void
    {
        self::assertSame(6_000_000, IntegerMath::multiply(6, 1_000_000));
        self::assertSame(-\PHP_INT_MAX, IntegerMath::multiply(\PHP_INT_MAX, -1));
    }

    public function testAProductOutOfRangeIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);
        $this->expectExceptionMessageIs('The computation leaves the range of a native integer.');

        IntegerMath::multiply(\PHP_INT_MAX, 2);
    }

    public function testAProductOfTheSmallestIntegerIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        IntegerMath::multiply(intdiv(\PHP_INT_MIN, 2), 2);
    }

    public function testADifferenceInRangeIsExact(): void
    {
        self::assertSame(-\PHP_INT_MAX, IntegerMath::subtract(0, \PHP_INT_MAX));
        self::assertSame(4, IntegerMath::subtract(10, 6));
    }

    public function testADifferenceOutOfRangeIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        IntegerMath::subtract(\PHP_INT_MAX, -1);
    }

    public function testADifferenceOfTheSmallestIntegerIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        IntegerMath::subtract(-\PHP_INT_MAX, 1);
    }

    public function testARateIsTheRatioInPartsPerMillion(): void
    {
        self::assertSame(666_667, IntegerMath::rate(2, 3, RoundingMode::HalfAwayFromZero)->partsPerMillion);
        self::assertSame(666_666, IntegerMath::rate(2, 3, RoundingMode::TowardsZero)->partsPerMillion);
    }

    public function testTheCostShareIsTheComplementOfTheMargin(): void
    {
        self::assertSame(600_000, IntegerMath::costShare(Rate::ofPercent('40')));
        self::assertSame(1, IntegerMath::costShare(Rate::of(999_999)));
        self::assertSame(1_500_000, IntegerMath::costShare(Rate::ofPercent('-50')));
    }

    public function testACostShareOfZeroIsRefused(): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs('A margin of 100 % leaves no room for the cost: a target margin is below 100 %.');

        IntegerMath::costShare(Rate::of(1_000_000));
    }
}
