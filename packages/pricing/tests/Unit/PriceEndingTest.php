<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Exception\InvalidAmount;
use Phil\Money\Money;
use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\PriceEnding;
use Phil\Pricing\PriceEndingBands;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(PriceEnding::class)]
#[CoversClass(PriceEndingBands::class)]
#[CoversClass(InvalidPricing::class)]
final class PriceEndingTest extends TestCase
{
    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    private function ending(string $step, string $ending, RoundingMode $mode = RoundingMode::PositiveInfinity): PriceEnding
    {
        return PriceEnding::ofDecimals($step, $ending, Currency::of('CAD'), $mode);
    }

    /**
     * Step, ending, price, ended price — up, the default.
     *
     * @return iterable<string, array{string, string, int, int}>
     */
    public static function upwards(): iterable
    {
        yield '.99, 19.37' => ['1', '0.99', 1_937, 1_999];
        yield '.99, 19.99 stays' => ['1', '0.99', 1_999, 1_999];
        yield '.99, 20.00' => ['1', '0.99', 2_000, 2_099];
        yield '.99, 0.00' => ['1', '0.99', 0, 99];
        yield '.95, 19.96' => ['1', '0.95', 1_996, 2_095];
        yield 'whole unit, 19.01' => ['1', '0', 1_901, 2_000];
        yield 'whole unit, 19.00 stays' => ['1', '0', 1_900, 1_900];
        yield '9.99 every 10, 21.50' => ['10', '9.99', 2_150, 2_999];
        yield '.49 or .99, 1.50' => ['0.5', '0.49', 150, 199];
        yield '.49 or .99, 1.20' => ['0.5', '0.49', 120, 149];
    }

    #[DataProvider('upwards')]
    public function testAPriceIsEndedUpByDefault(string $step, string $ending, int $price, int $ended): void
    {
        self::assertSame($ended, $this->ending($step, $ending)->apply($this->cad($price))->minorUnits);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function nearest(): iterable
    {
        yield '19.37 goes down' => [1_937, 1_899];
        yield '19.50 goes up' => [1_950, 1_999];
        yield '19.49 is a tie, it goes up' => [1_949, 1_999];
        yield '19.48 goes down' => [1_948, 1_899];
        yield '0.40 would be below zero, it goes up' => [40, 99];
        yield '0.00 would be below zero, it goes up' => [0, 99];
    }

    #[DataProvider('nearest')]
    public function testAPriceIsEndedToTheNearest(int $price, int $ended): void
    {
        self::assertSame($ended, $this->ending('1', '0.99', RoundingMode::HalfAwayFromZero)->apply($this->cad($price))->minorUnits);
    }

    public function testAPriceIsEndedDown(): void
    {
        $ending = $this->ending('1', '0.99', RoundingMode::NegativeInfinity);

        self::assertSame(1_899, $ending->apply($this->cad(1_998))->minorUnits);
        self::assertSame(1_999, $ending->apply($this->cad(1_999))->minorUnits);
        self::assertSame(99, $ending->apply($this->cad(50))->minorUnits);
    }

    public function testAnEndedPriceIsNeverBelowTheFloor(): void
    {
        $nearest = $this->ending('1', '0.99', RoundingMode::HalfAwayFromZero);

        self::assertSame(1_999, $nearest->apply($this->cad(1_937), $this->cad(1_920))->minorUnits);
        self::assertSame(1_899, $nearest->apply($this->cad(1_937), $this->cad(1_899))->minorUnits);
        self::assertSame(1_999, $nearest->apply($this->cad(1_937), $this->cad(1_900))->minorUnits);
        self::assertSame(2_099, $this->ending('1', '0.99')->apply($this->cad(1_937), $this->cad(2_000))->minorUnits);
        self::assertSame(1_999, $this->ending('1', '0.99')->apply($this->cad(1_937), $this->cad(1_999))->minorUnits);
    }

    public function testANegativeFloorIsZero(): void
    {
        self::assertSame(99, $this->ending('1', '0.99', RoundingMode::HalfAwayFromZero)->apply($this->cad(40), $this->cad(-500))->minorUnits);
    }

    public function testAFloorInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->ending('1', '0.99')->apply($this->cad(1_937), Money::of(-1, Currency::of('USD')));
    }

    public function testAPriceInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->ending('1', '0.99')->apply(Money::of(1_937, Currency::of('USD')));
    }

    public function testANegativePriceIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIs('A price ending applies to a selling price, -0.01 is negative: a refund negates the ended price.');

        $this->ending('1', '0.99')->apply($this->cad(-1));
    }

    public function testTheEndingKeepsItsParts(): void
    {
        $ending = PriceEnding::of(Money::of(100, Currency::of('JPY')), Money::of(80, Currency::of('JPY')));

        self::assertSame(100, $ending->step->minorUnits);
        self::assertSame(80, $ending->ending->minorUnits);
        self::assertSame(RoundingMode::PositiveInfinity, $ending->mode);
        self::assertSame(1_280, $ending->apply(Money::of(1_234, Currency::of('JPY')))->minorUnits);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidEndings(): iterable
    {
        yield 'a zero step' => ['0', '0', 'A price ending repeats every step, 0.00 is not above zero.'];
        yield 'a negative step' => ['-1', '0', 'A price ending repeats every step, -1.00 is not above zero.'];
        yield 'an ending equal to the step' => ['1', '1', 'The ending 1.00 is not from zero to below its step 1.00.'];
        yield 'an ending above the step' => ['1', '1.5', 'The ending 1.50 is not from zero to below its step 1.00.'];
        yield 'a negative ending' => ['1', '-0.01', 'The ending -0.01 is not from zero to below its step 1.00.'];
    }

    #[DataProvider('invalidEndings')]
    public function testAnInvalidEndingIsRefused(string $step, string $ending, string $message): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIs($message);

        $this->ending($step, $ending);
    }

    public function testAnEndingInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        PriceEnding::of($this->cad(100), Money::of(99, Currency::of('USD')));
    }

    public function testADecimalTooPreciseIsRefused(): void
    {
        $this->expectException(InvalidAmount::class);

        PriceEnding::ofDecimals('1', '0.999', Currency::of('CAD'));
    }

    public function testAnEndedPriceOutOfRangeIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        PriceEnding::of($this->cad(intdiv(\PHP_INT_MAX, 2) + 1), $this->cad(0))->apply($this->cad(\PHP_INT_MAX));
    }

    public function testBandsChooseTheEndingByPrice(): void
    {
        $bands = PriceEndingBands::startingWith($this->ending('1', '0.99'))
            ->from($this->cad(1_000), $this->ending('1', '0.95'))
            ->from($this->cad(10_000), $this->ending('1', '0'));

        self::assertSame(999, $bands->apply($this->cad(937))->minorUnits);
        self::assertSame(1_095, $bands->apply($this->cad(1_000))->minorUnits);
        self::assertSame(1_095, $bands->apply($this->cad(1_001))->minorUnits);
        self::assertSame(10_000, $bands->apply($this->cad(10_000))->minorUnits);
        self::assertSame(12_400, $bands->apply($this->cad(12_345))->minorUnits);
    }

    public function testTheBandOfThePriceBeforeItIsEndedIsKept(): void
    {
        $bands = PriceEndingBands::startingWith($this->ending('1', '0.99'))->from($this->cad(1_000), $this->ending('1', '0'));

        self::assertSame(1_099, $bands->apply($this->cad(999), $this->cad(1_001))->minorUnits);
        self::assertSame(1_100, $bands->apply($this->cad(1_000), $this->cad(1_001))->minorUnits);
    }

    public function testBandsWithoutABandUseTheirFirstRule(): void
    {
        self::assertSame(1_999, PriceEndingBands::startingWith($this->ending('1', '0.99'))->apply($this->cad(1_937))->minorUnits);
    }

    public function testBandsLeaveTheOriginalUntouched(): void
    {
        $first = PriceEndingBands::startingWith($this->ending('1', '0.99'));
        $first->from($this->cad(1_000), $this->ending('1', '0'));

        self::assertSame(1_999, $first->apply($this->cad(1_937))->minorUnits);
    }

    public function testBandsOutOfOrderAreRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIs('The band from 10.00 does not start above the band from 10.00: bands are added in ascending order.');

        PriceEndingBands::startingWith($this->ending('1', '0.99'))
            ->from($this->cad(1_000), $this->ending('1', '0.95'))
            ->from($this->cad(1_000), $this->ending('1', '0'));
    }

    public function testABandAtZeroIsRefused(): void
    {
        $this->expectException(InvalidPricing::class);
        $this->expectExceptionMessageIs('A band starts above zero, 0.00 does not: the first ending covers every price below the first band.');

        PriceEndingBands::startingWith($this->ending('1', '0.99'))->from($this->cad(0), $this->ending('1', '0'));
    }

    public function testABandInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        PriceEndingBands::startingWith($this->ending('1', '0.99'))
            ->from($this->cad(1_000), $this->ending('1', '0'))
            ->from(Money::of(2_000, Currency::of('USD')), $this->ending('1', '0'));
    }

    public function testBandsPassTheFloor(): void
    {
        $bands = PriceEndingBands::startingWith($this->ending('1', '0.99', RoundingMode::HalfAwayFromZero));

        self::assertSame(1_999, $bands->apply($this->cad(1_910), $this->cad(1_905))->minorUnits);
    }
}
