<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Unit;

use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Exception\MeasurementOutOfRange;
use Phil\Measurement\Fraction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(Fraction::class)]
#[CoversClass(InvalidMeasurement::class)]
#[CoversClass(MeasurementOutOfRange::class)]
final class FractionTest extends TestCase
{
    public function testItKeepsLowestTermsWithAPositiveDenominator(): void
    {
        $fraction = Fraction::of(6, -8);

        self::assertSame(-3, $fraction->numerator);
        self::assertSame(4, $fraction->denominator);
        self::assertSame(0, Fraction::of(0, 5)->numerator);
        self::assertSame(1, Fraction::of(0, 5)->denominator);
        self::assertSame(1, Fraction::of(7)->denominator);
    }

    public function testItRefusesADivisionByZeroAndTheMinimumInteger(): void
    {
        try {
            Fraction::of(1, 0);
            self::fail('A zero denominator is refused.');
        } catch (InvalidMeasurement $invalidMeasurement) {
            self::assertStringContainsString('division by zero', $invalidMeasurement->getMessage());
        }

        $this->expectException(MeasurementOutOfRange::class);
        Fraction::of(\PHP_INT_MIN);
    }

    public function testTheMinimumIntegerIsRefusedAsADenominator(): void
    {
        $this->expectException(MeasurementOutOfRange::class);
        Fraction::of(1, \PHP_INT_MIN);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function decimals(): iterable
    {
        yield 'integer' => ['1200', 1200, 1];
        yield 'decimal' => ['12.5', 25, 2];
        yield 'negative' => ['-0.25', -1, 4];
        yield 'trailing zeros' => ['2.500', 5, 2];
        yield 'leading zeros' => ['007', 7, 1];
        yield 'quotient' => ['5/9', 5, 9];
        yield 'negative quotient' => ['-1/3', -1, 3];
        yield 'decimal quotient' => ['1.5/0.25', 6, 1];
        yield 'eighteen fraction digits' => ['0.000000000000000001', 1, 1_000_000_000_000_000_000];
        yield 'eighteen integer digits' => ['999999999999999999', 999_999_999_999_999_999, 1];
    }

    #[DataProvider('decimals')]
    public function testItParsesDecimalsAndQuotients(string $text, int $numerator, int $denominator): void
    {
        $fraction = Fraction::parse($text);

        self::assertSame([$numerator, $denominator], [$fraction->numerator, $fraction->denominator]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notDecimals(): iterable
    {
        yield 'empty' => [''];
        yield 'plus sign' => ['+1'];
        yield 'dot only' => ['.5'];
        yield 'trailing dot' => ['5.'];
        yield 'exponent' => ['1e3'];
        yield 'spaces' => [' 1'];
        yield 'line break' => ["1\n"];
        yield 'negative denominator' => ['1/-3'];
        yield 'two slashes' => ['1/2/3'];
        yield 'comma' => ['1,5'];
    }

    #[DataProvider('notDecimals')]
    public function testItRefusesWhatIsNotADecimal(string $text): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains('is not a decimal');
        Fraction::parse($text);
    }

    public function testItRefusesMoreThanEighteenSignificantDigits(): void
    {
        try {
            Fraction::parse('0.0000000000000000001');
            self::fail('Nineteen fraction digits are refused.');
        } catch (MeasurementOutOfRange $measurementOutOfRange) {
            self::assertStringContainsString('more than 18 significant digits', $measurementOutOfRange->getMessage());
        }

        $this->expectException(MeasurementOutOfRange::class);
        Fraction::parse('1000000000000000000');
    }

    public function testTrailingZerosDoNotCountAsDigits(): void
    {
        self::assertSame(1, Fraction::parse('1.0000000000000000000000')->numerator);
        self::assertSame(5, Fraction::parse('0000000000000000000005')->numerator);
    }

    public function testArithmeticIsExact(): void
    {
        $third = Fraction::of(1, 3);

        self::assertSame('1', $third->add($third)->add($third)->toCanonicalDecimal());
        self::assertSame('1/6', $this->written($third->subtract(Fraction::of(1, 6))));
        self::assertSame('2/9', $this->written($third->multiply(Fraction::of(2, 3))));
        self::assertSame('1/2', $this->written($third->divide(Fraction::of(2, 3))));
        self::assertSame('3/1', $this->written($third->reciprocal()));
        self::assertSame('-1/3', $this->written($third->negate()));
        self::assertSame('1/3', $this->written($third->negate()->abs()));
        self::assertSame('0/1', $this->written(Fraction::of(0)->multiply(Fraction::of(2, 3))));
        self::assertSame('0/1', $this->written(Fraction::of(2, 3)->multiply(Fraction::of(0))));
        self::assertSame(-1, $third->negate()->sign());
        self::assertSame(0, Fraction::of(0)->sign());
        self::assertSame(1, $third->sign());
    }

    public function testASumThatNoLongerFitsIsRoundedToEighteenSignificantDigits(): void
    {
        $left = Fraction::of(1, 999_999_999_989);
        $right = Fraction::of(1, 999_999_999_959);

        // 2.000000000052e-12, to 18 digits after the point: the denominator of the result fits.
        self::assertSame('1/500000000000', $this->written($left->add($right)));
        self::assertSame('-1/500000000000', $this->written($left->negate()->add($right->negate())));
        self::assertSame('0.000000000001000000000011', Fraction::of(1, 999_999_999_989)->toDecimal(24, RoundingMode::HalfEven));
        self::assertSame('0.000000000000000000', $left->subtract(Fraction::of(1, 999_999_999_989))->toDecimal(18, RoundingMode::HalfEven));
    }

    public function testASumOfNumbersFarApartKeepsTheDigitsOfTheCoarserOne(): void
    {
        $large = Fraction::of(123_456_789_012_345_678, 7);
        $small = Fraction::of(1, 999_999_999_989);

        self::assertSame('17636684144620811.1', $large->add($small)->toTrimmedDecimal(6, RoundingMode::HalfEven));
        self::assertSame('17636684144620811.1', $small->add($large)->toTrimmedDecimal(6, RoundingMode::HalfEven));
    }

    public function testAProductThatNoLongerFitsIsRoundedToEighteenSignificantDigits(): void
    {
        $psi = Fraction::parse('44482216152605/6451600000');
        $value = Fraction::parse('12.345678');

        self::assertSame('85120.4534296082', $value->multiply($psi)->toTrimmedDecimal(10, RoundingMode::HalfEven));
        self::assertSame('-85120.4534296082', $value->negate()->multiply($psi)->toTrimmedDecimal(10, RoundingMode::HalfEven));
        self::assertSame('12.345678', $value->multiply($psi)->divide($psi)->toTrimmedDecimal(12, RoundingMode::HalfEven));
    }

    public function testARoundedProductKeepsEighteenSignificantDigitsOfASmallValue(): void
    {
        $product = Fraction::of(1, 999_999_999_989)->multiply(Fraction::of(1, 999_999_937));

        // 1.000000063e-21 is below the last of 18 digits after the point.
        self::assertSame('0/1', $this->written($product));
        self::assertSame('0.00000000000000000000', Fraction::of(1, 999_999_999_989)->multiply(Fraction::parse('0.00000001'))->toDecimal(20, RoundingMode::HalfEven));
        self::assertSame('-0.000000004000000252', Fraction::of(-4_000_000_000_007, 999_999_999_989)->multiply(Fraction::of(1, 999_999_937))->toDecimal(18, RoundingMode::HalfEven));
    }

    public function testARoundedProductRoundsHalfToEven(): void
    {
        // 999999999999999999 × 3 = 2999999999999999997: nineteen digits, the last one dropped.
        $product = Fraction::of(999_999_999_999_999_999, 1_000_000_000_000_000_000)->multiply(Fraction::of(3_000_000_000_000_000_001, 1_000_000_000_000_000_000));

        // 2.999999999999999998 rounded to 18 significant digits.
        self::assertSame('3/1', $this->written($product));
    }

    public function testAProductBeyondTheNativeRangeIsRefused(): void
    {
        $this->expectException(MeasurementOutOfRange::class);
        $this->expectExceptionMessageIsOrContains('leaves the range of native integers');
        Fraction::of(\PHP_INT_MAX)->multiply(Fraction::of(3));
    }

    public function testASumBeyondTheNativeRangeIsRefused(): void
    {
        $this->expectException(MeasurementOutOfRange::class);
        Fraction::of(\PHP_INT_MAX)->add(Fraction::of(\PHP_INT_MAX));
    }

    public function testComparisonNeverOverflows(): void
    {
        $large = Fraction::of(\PHP_INT_MAX, 3);
        $larger = Fraction::of(\PHP_INT_MAX - 1, 2);

        self::assertSame(-1, $large->compare($larger));
        self::assertSame(1, $larger->compare($large));
        self::assertSame(0, Fraction::of(2, 4)->compare(Fraction::of(1, 2)));
        self::assertSame(-1, Fraction::of(-1, 3)->compare(Fraction::of(-1, 4)));
        self::assertSame(1, Fraction::of(-1, 4)->compare(Fraction::of(-1, 3)));
        self::assertSame(-1, Fraction::of(-7, 2)->compare(Fraction::of(-3)));
        self::assertSame(1, Fraction::of(5, 3)->compare(Fraction::of(1)));
        self::assertSame(-1, Fraction::of(1)->compare(Fraction::of(5, 3)));
        self::assertSame(-1, Fraction::of(\PHP_INT_MAX - 2, \PHP_INT_MAX)->compare(Fraction::of(\PHP_INT_MAX - 1, \PHP_INT_MAX)));
    }

    public function testItTellsATerminatingDecimal(): void
    {
        self::assertTrue(Fraction::of(1, 8)->isTerminating());
        self::assertTrue(Fraction::of(3, 20)->isTerminating());
        self::assertTrue(Fraction::of(7)->isTerminating());
        self::assertFalse(Fraction::of(1, 3)->isTerminating());
        self::assertFalse(Fraction::of(1, 30)->isTerminating());
    }

    /**
     * @return iterable<string, array{string, int, RoundingMode, string}>
     */
    public static function roundings(): iterable
    {
        yield 'third' => ['1/3', 2, RoundingMode::HalfEven, '0.33'];
        yield 'two thirds' => ['2/3', 2, RoundingMode::HalfEven, '0.67'];
        yield 'half even down' => ['-5/8', 2, RoundingMode::HalfEven, '-0.62'];
        yield 'half even up' => ['0.375', 2, RoundingMode::HalfEven, '0.38'];
        yield 'half odd' => ['0.625', 2, RoundingMode::HalfOdd, '0.63'];
        yield 'half odd down' => ['0.635', 2, RoundingMode::HalfOdd, '0.63'];
        yield 'half away' => ['0.125', 2, RoundingMode::HalfAwayFromZero, '0.13'];
        yield 'half away negative' => ['-0.125', 2, RoundingMode::HalfAwayFromZero, '-0.13'];
        yield 'half towards' => ['0.125', 2, RoundingMode::HalfTowardsZero, '0.12'];
        yield 'half towards above' => ['0.1251', 2, RoundingMode::HalfTowardsZero, '0.13'];
        yield 'towards zero' => ['0.129', 2, RoundingMode::TowardsZero, '0.12'];
        yield 'away from zero' => ['0.121', 2, RoundingMode::AwayFromZero, '0.13'];
        yield 'positive infinity' => ['0.121', 2, RoundingMode::PositiveInfinity, '0.13'];
        yield 'positive infinity negative' => ['-0.129', 2, RoundingMode::PositiveInfinity, '-0.12'];
        yield 'negative infinity' => ['-0.121', 2, RoundingMode::NegativeInfinity, '-0.13'];
        yield 'negative infinity positive' => ['0.129', 2, RoundingMode::NegativeInfinity, '0.12'];
        yield 'carry' => ['9.995', 2, RoundingMode::HalfEven, '10.00'];
        yield 'no fraction digits' => ['2.5', 0, RoundingMode::HalfEven, '2'];
        yield 'no fraction digits up' => ['3.5', 0, RoundingMode::HalfEven, '4'];
        yield 'negative zero' => ['-0.001', 2, RoundingMode::HalfEven, '0.00'];
        yield 'exact' => ['12.5', 3, RoundingMode::HalfEven, '12.500'];
    }

    #[DataProvider('roundings')]
    public function testItWritesARoundedDecimal(string $value, int $digits, RoundingMode $mode, string $expected): void
    {
        self::assertSame($expected, Fraction::parse($value)->toDecimal($digits, $mode));
    }

    public function testTheDigitsOfALargeDenominatorNeverOverflow(): void
    {
        self::assertSame('0.333333333333333333', Fraction::of(intdiv(\PHP_INT_MAX, 3), \PHP_INT_MAX - 1)->toDecimal(18, RoundingMode::HalfEven));
        self::assertSame('0.999999999999999999892', Fraction::of(\PHP_INT_MAX - 1, \PHP_INT_MAX)->toDecimal(21, RoundingMode::HalfEven));
    }

    public function testItRefusesNegativeFractionDigits(): void
    {
        $this->expectException(InvalidMeasurement::class);
        $this->expectExceptionMessageIsOrContains('-1 given');
        Fraction::of(1)->toDecimal(-1, RoundingMode::HalfEven);
    }

    public function testRoundingGivesAnExactNumberAgain(): void
    {
        self::assertSame('333/1000', $this->written(Fraction::of(1, 3)->round(3, RoundingMode::HalfEven)));
        self::assertSame('-1/2', $this->written(Fraction::parse('-0.45')->round(1, RoundingMode::AwayFromZero)));
        self::assertSame('9223372036854775807/1', $this->written(Fraction::of(\PHP_INT_MAX)->round(0, RoundingMode::HalfEven)));
    }

    public function testRoundingRefusesMoreThanEighteenDigitsOrAnOverflow(): void
    {
        try {
            Fraction::of(1, 3)->round(19, RoundingMode::HalfEven);
            self::fail('Nineteen digits are refused.');
        } catch (MeasurementOutOfRange) {
            self::assertSame('333333333333333333/1000000000000000000', $this->written(Fraction::of(1, 3)->round(18, RoundingMode::HalfEven)));
        }

        $this->expectException(MeasurementOutOfRange::class);
        Fraction::of(\PHP_INT_MAX, 2)->multiply(Fraction::of(2))->add(Fraction::of(1, 2))->round(0, RoundingMode::AwayFromZero);
    }

    public function testTheTrimmedDecimalHasNoUselessZeros(): void
    {
        self::assertSame('1.25', Fraction::of(5, 4)->toTrimmedDecimal(4, RoundingMode::HalfEven));
        self::assertSame('0.3333', Fraction::of(1, 3)->toTrimmedDecimal(4, RoundingMode::HalfEven));
        self::assertSame('2', Fraction::parse('2.0001')->toTrimmedDecimal(3, RoundingMode::HalfEven));
        self::assertSame('120', Fraction::of(120)->toTrimmedDecimal(0, RoundingMode::HalfEven));
        self::assertSame('120', Fraction::of(120)->toTrimmedDecimal(2, RoundingMode::HalfEven));
    }

    public function testTheCanonicalDecimalReadsBackWhenItCan(): void
    {
        self::assertSame('0.45359237', Fraction::parse('0.45359237')->toCanonicalDecimal());
        self::assertSame('0.333333333333333333', Fraction::of(1, 3)->toCanonicalDecimal());
        self::assertSame('33.3333333333333333', Fraction::of(100, 3)->toCanonicalDecimal());
        self::assertSame('123456789012345678', Fraction::of(123_456_789_012_345_678)->toCanonicalDecimal());
        self::assertSame('9223372036854775807', Fraction::of(\PHP_INT_MAX)->toCanonicalDecimal());
        self::assertSame('0.000000000000000001', Fraction::parse('0.000000000000000001')->toCanonicalDecimal());
        self::assertSame('1234567.5', Fraction::parse('1234567.5')->toCanonicalDecimal());
    }

    public function testAnExactQuotientIsNeverRoundedAway(): void
    {
        self::assertSame('0.5', Fraction::of(1, 2)->toDecimal(1, RoundingMode::AwayFromZero));
        self::assertSame('0.5', Fraction::of(1, 2)->toDecimal(1, RoundingMode::PositiveInfinity));
        self::assertSame('-0.5', Fraction::of(-1, 2)->toDecimal(1, RoundingMode::NegativeInfinity));
        self::assertSame('0.5', Fraction::of(1, 2)->toDecimal(1, RoundingMode::TowardsZero));
        self::assertSame('1.25', Fraction::of(5, 4)->toDecimal(2, RoundingMode::TowardsZero));
        self::assertSame('0.125', Fraction::of(1, 8)->toDecimal(3, RoundingMode::TowardsZero));
        self::assertSame('0.4375', Fraction::of(7, 16)->toDecimal(4, RoundingMode::TowardsZero));
        self::assertSame('0.3333', Fraction::of(1, 3)->toDecimal(4, RoundingMode::TowardsZero));
        self::assertSame('0.3334', Fraction::of(1, 3)->toDecimal(4, RoundingMode::AwayFromZero));
    }

    public function testTheCanonicalDecimalOfALongTerminatingDecimalIsCutToEighteenDigits(): void
    {
        // 2^-30 ends after 30 digits: 9.31322574615478515625e-10 is cut to 18 digits after the point.
        self::assertSame('0.000000000931322575', Fraction::of(1, 1_073_741_824)->toCanonicalDecimal());
    }

    public function testNegativeNumbersAreComparedByTheirExactValue(): void
    {
        self::assertSame(1, Fraction::of(-12, 5)->compare(Fraction::of(-10, 4)));
        self::assertSame(-1, Fraction::of(-10, 4)->compare(Fraction::of(-12, 5)));
        self::assertSame(-1, Fraction::of(-12, 2)->compare(Fraction::of(-11, 2)));
        self::assertSame(1, Fraction::of(-11, 2)->compare(Fraction::of(-12, 2)));
        self::assertSame(-1, Fraction::of(-6)->compare(Fraction::of(-11, 2)));
    }

    /**
     * Sums whose exact result no longer fits: the digits that are read, dropped and rounded half to even, with the
     * exact value to 18 significant digits as the comment of each one gives it.
     *
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function roundedSums(): iterable
    {
        // 0.003243406095489486498…
        yield 'rest above five' => [313_815, 96_754_767, 7_993, 732_720_497_671_447, '1621703047744743/500000000000000000'];
        // -0.009532498407581416553…
        yield 'rest after two digits' => [7, 86_608_871_021_631_514, -8_456_315_412, 887_103_784_384, '-9532498407581417/1000000000000000000'];
        // 0.000000013086698880536…, 18 digits after the point
        yield 'small rest' => [8_950_542_948, 683_941_966_118_543_251, -508, 900_940_475_135_622_528, '13086698881/1000000000000000000'];
        // -0.030184588823496937524…
        yield 'negative sum' => [-81, 25_501_343, -16_003_696_305_665_635, 530_250_076_763_563_001, '-15092294411748469/500000000000000000'];
        // 0.011368120118662523521…
        yield 'last kept digit' => [-378_911, 554_564_995_052_583, 31_238_721_674_175, 2_747_923_092_873_661, '2842030029665631/250000000000000000'];
        // 28173151441390.260822…, 18 significant digits with the coarser operand
        yield 'far apart' => [-7_069_846_109, 99_991_726_244_891, 51_725_906_046_392_519, 1_836, '17608219650868913/625'];
        // -823.4642383674040322…, to the 14 digits of the coarser operand
        yield 'coarser operand' => [-788_447_450_024, 751_110_055, 3_129_665_951_380, 13_833_060_087, '-82346423836740403/100000000000000'];
        // 5.41e-19, up to 1e-18
        yield 'below the last digit' => [3, 8_810_988_083, -3, 8_810_988_097, '1/1000000000000000000'];
        // 2.17e-18, to 2e-18
        yield 'two units' => [5, 5_471_922_368, -5, 5_471_922_381, '1/500000000000000000'];
    }

    #[DataProvider('roundedSums')]
    public function testASumThatNoLongerFitsIsRoundedHalfToEven(int $leftNumerator, int $leftDenominator, int $rightNumerator, int $rightDenominator, string $expected): void
    {
        self::assertSame($expected, $this->written(Fraction::of($leftNumerator, $leftDenominator)->add(Fraction::of($rightNumerator, $rightDenominator))));
    }

    /**
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function roundedProducts(): iterable
    {
        // 183.598177054727583427…
        yield 'zeros after the point' => [255_734_089_218_880, 549_459_060_686_589_747, -394_471, 1, '-183598177054727583/1000000000000000'];
        // 2617004079289804387.81…, 18 significant digits
        yield 'integer part' => [-39_399_942_958_586, 3_866_309, 18_490_040_540_185, 72, '-2617004079289804390/1'];
        // 4.77e-20 is below the last digit, and a negative zero is zero
        yield 'negative below the last digit' => [1, 4_578_889_060, -1, 4_578_889_080, '0/1'];
        // 45.000000000000000625, to 18 significant digits
        yield 'last digits of the product' => [5, 1, 9_000_000_000_000_000_026, 999_999_999_999_999_989, '225000000000000003/5000000000000000'];
        // 1e-20: the product written out in full ends in zeros, nothing is added to it
        yield 'a power of ten' => [1, 10_000_000_000, 1, 10_000_000_000, '0/1'];
    }

    #[DataProvider('roundedProducts')]
    public function testAProductThatNoLongerFitsIsRoundedAsItsDigitsSay(int $leftNumerator, int $leftDenominator, int $rightNumerator, int $rightDenominator, string $expected): void
    {
        self::assertSame($expected, $this->written(Fraction::of($leftNumerator, $leftDenominator)->multiply(Fraction::of($rightNumerator, $rightDenominator))));
    }

    private function written(Fraction $fraction): string
    {
        return $fraction->numerator.'/'.$fraction->denominator;
    }
}
