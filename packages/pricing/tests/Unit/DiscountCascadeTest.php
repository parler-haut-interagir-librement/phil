<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\DiscountCascade;
use Phil\Pricing\DiscountedPrice;
use Phil\Pricing\DiscountStep;
use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidRate;
use Phil\Pricing\Exception\UndefinedRate;
use Phil\Pricing\Rate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;

#[CoversClass(DiscountCascade::class)]
#[CoversClass(DiscountedPrice::class)]
#[CoversClass(DiscountStep::class)]
#[CoversClass(InvalidRate::class)]
#[CoversClass(UndefinedRate::class)]
final class DiscountCascadeTest extends TestCase
{
    private DiscountCascade $cascade;

    protected function setUp(): void
    {
        $this->cascade = new DiscountCascade();
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    public function testEachDiscountAppliesToWhatThePreviousOneLeft(): void
    {
        $result = $this->cascade->apply($this->cad(10_000), [Rate::ofPercent('20'), Rate::ofPercent('10')]);

        self::assertSame(10_000, $result->original->minorUnits);
        self::assertCount(2, $result->steps);
        self::assertSame(200_000, $result->steps[0]->rate->partsPerMillion);
        self::assertSame(10_000, $result->steps[0]->before->minorUnits);
        self::assertSame(2_000, $result->steps[0]->discount->minorUnits);
        self::assertSame(8_000, $result->steps[0]->after->minorUnits);
        self::assertSame(100_000, $result->steps[1]->rate->partsPerMillion);
        self::assertSame(8_000, $result->steps[1]->before->minorUnits);
        self::assertSame(800, $result->steps[1]->discount->minorUnits);
        self::assertSame(7_200, $result->steps[1]->after->minorUnits);
        self::assertSame(7_200, $result->price->minorUnits);
        self::assertSame(2_800, $result->totalDiscount()->minorUnits);
        self::assertSame(280_000, $result->effectiveRate()->partsPerMillion);
    }

    public function testTheOrderIsKept(): void
    {
        $result = $this->cascade->apply($this->cad(999), [Rate::ofPercent('10'), Rate::ofPercent('15')]);
        $reversed = $this->cascade->apply($this->cad(999), [Rate::ofPercent('15'), Rate::ofPercent('10')]);

        self::assertSame([100, 135], [$result->steps[0]->discount->minorUnits, $result->steps[1]->discount->minorUnits]);
        self::assertSame([150, 85], [$reversed->steps[0]->discount->minorUnits, $reversed->steps[1]->discount->minorUnits]);
        self::assertSame(764, $result->price->minorUnits);
        self::assertSame(764, $reversed->price->minorUnits);
    }

    public function testEachStepIsRoundedOnItsOwn(): void
    {
        // 12.5 % of 0.99 is 12.375 cents, rounded to 12; 12.5 % of 0.87 is 10.875, rounded to 11.
        $result = $this->cascade->apply($this->cad(99), [Rate::ofPercent('12.5'), Rate::ofPercent('12.5')]);

        self::assertSame(12, $result->steps[0]->discount->minorUnits);
        self::assertSame(11, $result->steps[1]->discount->minorUnits);
        self::assertSame(76, $result->price->minorUnits);
    }

    public function testTheRoundingIsChosen(): void
    {
        $result = $this->cascade->apply($this->cad(99), [Rate::ofPercent('12.5')], RoundingMode::PositiveInfinity);

        self::assertSame(13, $result->steps[0]->discount->minorUnits);
        self::assertSame(86, $result->price->minorUnits);
    }

    public function testAnExactHalfIsRoundedAwayFromZero(): void
    {
        self::assertSame(1, $this->cascade->apply($this->cad(1), [Rate::ofPercent('50')])->steps[0]->discount->minorUnits);
        self::assertSame(-1, $this->cascade->apply($this->cad(-1), [Rate::ofPercent('50')])->steps[0]->discount->minorUnits);
    }

    public function testARefundGivesTheNegatedSteps(): void
    {
        $sale = $this->cascade->apply($this->cad(99), [Rate::ofPercent('12.5'), Rate::ofPercent('12.5')]);
        $refund = $this->cascade->apply($this->cad(-99), [Rate::ofPercent('12.5'), Rate::ofPercent('12.5')]);

        foreach ($sale->steps as $index => $step) {
            self::assertSame(-$step->discount->minorUnits, $refund->steps[$index]->discount->minorUnits);
        }

        self::assertSame(-76, $refund->price->minorUnits);
        self::assertSame($sale->effectiveRate()->partsPerMillion, $refund->effectiveRate()->partsPerMillion);
    }

    public function testNoDiscountLeavesThePrice(): void
    {
        $result = $this->cascade->apply($this->cad(1_234), []);

        self::assertSame([], $result->steps);
        self::assertSame(1_234, $result->price->minorUnits);
        self::assertSame(0, $result->totalDiscount()->minorUnits);
        self::assertSame(0, $result->effectiveRate()->partsPerMillion);
    }

    public function testTheBoundsAreDiscounts(): void
    {
        $result = $this->cascade->apply($this->cad(1_234), [Rate::zero(), Rate::of(1_000_000), Rate::ofPercent('50')]);

        self::assertSame(0, $result->steps[0]->discount->minorUnits);
        self::assertSame(1_234, $result->steps[1]->discount->minorUnits);
        self::assertSame(0, $result->price->minorUnits);
        self::assertSame(1_000_000, $result->effectiveRate()->partsPerMillion);
    }

    public function testTheEffectiveRateRoundingIsChosen(): void
    {
        $result = $this->cascade->apply($this->cad(3), [Rate::ofPercent('33.3334')]);

        self::assertSame(1, $result->totalDiscount()->minorUnits);
        self::assertSame(333_333, $result->effectiveRate(RoundingMode::TowardsZero)->partsPerMillion);
        self::assertSame(333_334, $result->effectiveRate(RoundingMode::PositiveInfinity)->partsPerMillion);
    }

    public function testTheEffectiveRateOfAZeroPriceIsUndefined(): void
    {
        $result = $this->cascade->apply($this->cad(0), [Rate::ofPercent('10')]);

        self::assertSame(0, $result->price->minorUnits);

        $this->expectException(UndefinedRate::class);
        $this->expectExceptionMessageIs('The effective discount on a zero price is undefined.');

        $result->effectiveRate();
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function notDiscounts(): iterable
    {
        yield 'negative' => [-1, '-0.0001'];
        yield 'above one hundred percent' => [1_000_001, '100.0001'];
    }

    #[DataProvider('notDiscounts')]
    public function testARateOutsideZeroToOneHundredPercentIsRefused(int $rate, string $percent): void
    {
        $this->expectException(InvalidRate::class);
        $this->expectExceptionMessageIs(\sprintf('A discount of %s %% is not one: a discount is from 0 to 100 %%.', $percent));

        $this->cascade->apply($this->cad(1_000), [Rate::ofPercent('10'), Rate::of($rate)]);
    }

    public function testAPriceTooLargeToDiscountIsRefused(): void
    {
        $this->expectException(AmountOutOfRange::class);

        $this->cascade->apply($this->cad(\PHP_INT_MAX), [Rate::ofPercent('10')]);
    }

    public function testTheStepsKeepTheCurrency(): void
    {
        $result = $this->cascade->apply(Money::of(1_000, Currency::of('JPY')), [Rate::ofPercent('10')]);

        self::assertSame('JPY', $result->steps[0]->discount->currency->code);
        self::assertSame('JPY', $result->price->currency->code);
    }
}
