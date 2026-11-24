<?php

declare(strict_types=1);

namespace Phil\Pricing\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Pricing\DiscountCascade;
use Phil\Pricing\DiscountedPrice;
use Phil\Pricing\DiscountStep;
use Phil\Pricing\IntegerMath;
use Phil\Pricing\MarginCalculator;
use Phil\Pricing\MarginSummary;
use Phil\Pricing\PriceCalculator;
use Phil\Pricing\PricedLine;
use Phil\Pricing\PriceEnding;
use Phil\Pricing\Rate;
use Phil\Pricing\RetailPriceComparison;
use Phil\Pricing\SuggestedRetailPrice;
use Phil\Pricing\WholesalePriceList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RoundingMode;

/**
 * Properties checked on many generated cases, from a fixed seed so that a failure is replayed as it was found.
 */
#[CoversClass(MarginCalculator::class)]
#[CoversClass(PriceCalculator::class)]
#[CoversClass(DiscountCascade::class)]
#[CoversClass(DiscountedPrice::class)]
#[CoversClass(DiscountStep::class)]
#[CoversClass(PriceEnding::class)]
#[CoversClass(MarginSummary::class)]
#[CoversClass(PricedLine::class)]
#[CoversClass(IntegerMath::class)]
#[CoversClass(SuggestedRetailPrice::class)]
#[CoversClass(RetailPriceComparison::class)]
#[CoversClass(WholesalePriceList::class)]
final class PropertiesTest extends TestCase
{
    private const int CASES = 5_000;

    private Randomizer $random;

    protected function setUp(): void
    {
        $this->random = new Randomizer(new Mt19937(20_261_121));
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    public function testAMarginFromZeroToOneHundredPercentSurvivesTheRoundTripThroughAMarkup(): void
    {
        $calculator = new MarginCalculator();

        for ($i = 0; $i < self::CASES; ++$i) {
            $margin = Rate::of($this->random->getInt(0, 999_999));

            self::assertSame($margin->partsPerMillion, $calculator->marginFromMarkup($calculator->markupFromMargin($margin))->partsPerMillion);
        }
    }

    public function testANegativeMarginComesBackWithinTwoPartsPerMillion(): void
    {
        $calculator = new MarginCalculator();

        for ($i = 0; $i < self::CASES; ++$i) {
            $margin = $this->random->getInt(-1_000_000, 0);
            $back = $calculator->marginFromMarkup($calculator->markupFromMargin(Rate::of($margin)))->partsPerMillion;

            self::assertLessThanOrEqual(2, abs($back - $margin), (string) $margin);
        }
    }

    public function testAMarkupUpToOneHundredPercentComesBackWithinTwoPartsPerMillion(): void
    {
        $calculator = new MarginCalculator();

        for ($i = 0; $i < self::CASES; ++$i) {
            $markup = $this->random->getInt(0, 1_000_000);
            $back = $calculator->markupFromMargin($calculator->marginFromMarkup(Rate::of($markup)))->partsPerMillion;

            self::assertLessThanOrEqual(2, abs($back - $markup), (string) $markup);
        }
    }

    public function testThePriceForAMarginIsTheSmallestThatReachesIt(): void
    {
        $calculator = new PriceCalculator();

        for ($i = 0; $i < self::CASES; ++$i) {
            $cost = $this->random->getInt(1, 10_000_000);
            $margin = $this->random->getInt(-1_000_000, 990_000);
            $price = $calculator->priceForMargin($this->cad($cost), Rate::of($margin))->minorUnits;

            // (price − cost) / price ≥ margin, and one unit less falls short.
            self::assertGreaterThanOrEqual($margin * $price, ($price - $cost) * 1_000_000);
            self::assertLessThan($margin * ($price - 1), ($price - 1 - $cost) * 1_000_000);
        }
    }

    public function testThePriceForAMarkupIsTheSmallestThatReachesIt(): void
    {
        $calculator = new PriceCalculator();

        for ($i = 0; $i < self::CASES; ++$i) {
            $cost = $this->random->getInt(1, 10_000_000);
            $markup = $this->random->getInt(-1_000_000, 5_000_000);
            $price = $calculator->priceForMarkup($this->cad($cost), Rate::of($markup))->minorUnits;

            self::assertGreaterThanOrEqual($markup * $cost, ($price - $cost) * 1_000_000);
            self::assertLessThan($markup * $cost, ($price - 1 - $cost) * 1_000_000);
        }
    }

    public function testTheMarginRateOfAPriceForAMarginIsAtLeastTheTargetUpToRounding(): void
    {
        $prices = new PriceCalculator();
        $margins = new MarginCalculator();

        for ($i = 0; $i < self::CASES; ++$i) {
            $cost = $this->random->getInt(1, 10_000_000);
            $margin = $this->random->getInt(0, 990_000);
            $price = $prices->priceForMargin($this->cad($cost), Rate::of($margin));

            self::assertGreaterThanOrEqual($margin, $margins->marginRate($price, $this->cad($cost), RoundingMode::PositiveInfinity)->partsPerMillion);
        }
    }

    public function testACascadeChainsExactlyAndStaysWithinItsBounds(): void
    {
        $cascade = new DiscountCascade();

        for ($i = 0; $i < self::CASES; ++$i) {
            $price = $this->random->getInt(-10_000_000, 10_000_000);
            $rates = [];

            for ($count = $this->random->getInt(0, 5); $count > 0; --$count) {
                $rates[] = Rate::of($this->random->getInt(0, 1_000_000));
            }

            $result = $cascade->apply($this->cad($price), $rates);
            $current = $price;
            $discounted = 0;
            $exact = (float) $price;

            self::assertCount(\count($rates), $result->steps);

            foreach ($result->steps as $index => $step) {
                self::assertSame($rates[$index]->partsPerMillion, $step->rate->partsPerMillion);
                self::assertSame($current, $step->before->minorUnits);
                self::assertSame($step->before->minorUnits - $step->discount->minorUnits, $step->after->minorUnits);
                self::assertLessThanOrEqual(abs($step->before->minorUnits), abs($step->after->minorUnits));
                self::assertFalse($price > 0 && $step->after->minorUnits < 0);
                self::assertFalse($price < 0 && $step->after->minorUnits > 0);
                $current = $step->after->minorUnits;
                $discounted += $step->discount->minorUnits;
                $exact *= (1_000_000 - $rates[$index]->partsPerMillion) / 1_000_000;
            }

            self::assertSame($current, $result->price->minorUnits);
            self::assertSame($discounted, $result->totalDiscount()->minorUnits);
            // Each step is off by half a unit at most, and the next discounts shrink that error.
            self::assertLessThanOrEqual(0.5 * \count($rates) + 1e-6, abs($result->price->minorUnits - $exact));
        }
    }

    public function testACascadeOfARefundIsTheNegatedCascadeOfTheSale(): void
    {
        $cascade = new DiscountCascade();

        for ($i = 0; $i < self::CASES; ++$i) {
            $price = $this->random->getInt(0, 10_000_000);
            $rates = [Rate::of($this->random->getInt(0, 1_000_000)), Rate::of($this->random->getInt(0, 1_000_000))];

            self::assertSame(
                -$cascade->apply($this->cad($price), $rates)->price->minorUnits,
                $cascade->apply($this->cad(-$price), $rates)->price->minorUnits,
            );
        }
    }

    public function testAnEndedPriceIsAllowedNotBelowThePriceOrTheFloorAndLessThanAStepAbove(): void
    {
        for ($i = 0; $i < self::CASES; ++$i) {
            $step = $this->random->getInt(1, 5_000);
            $endingUnits = $this->random->getInt(0, $step - 1);
            $ending = PriceEnding::of($this->cad($step), $this->cad($endingUnits));
            $price = $this->random->getInt(0, 10_000_000);
            $floor = $this->random->getInt(-1_000, 10_000_000);
            $ended = $ending->apply($this->cad($price), $this->cad($floor))->minorUnits;
            $lowest = max($price, $floor);

            self::assertSame($endingUnits, $ended % $step);
            self::assertGreaterThanOrEqual($lowest, $ended);
            self::assertLessThan($lowest + $step, $ended);
        }
    }

    public function testANearestEndedPriceIsWithinHalfAStepOrAtTheFloor(): void
    {
        for ($i = 0; $i < self::CASES; ++$i) {
            $step = $this->random->getInt(1, 5_000);
            $endingUnits = $this->random->getInt(0, $step - 1);
            $ending = PriceEnding::of($this->cad($step), $this->cad($endingUnits), RoundingMode::HalfAwayFromZero);
            $price = $this->random->getInt(0, 10_000_000);
            $ended = $ending->apply($this->cad($price))->minorUnits;

            self::assertSame($endingUnits, $ended % $step);
            self::assertGreaterThanOrEqual(0, $ended);
            self::assertTrue(2 * abs($ended - $price) <= $step || $ended === $endingUnits, \sprintf('%d by %d+%d gave %d', $price, $step, $endingUnits, $ended));
        }
    }

    public function testAnEndedSuggestedPriceIsNeverBelowTheUnendedOneWhenItsRuleRoundsUp(): void
    {
        for ($i = 0; $i < self::CASES; ++$i) {
            $step = $this->random->getInt(1, 5_000);
            $ending = PriceEnding::of($this->cad($step), $this->cad($this->random->getInt(0, $step - 1)));
            $cost = $this->cad($this->random->getInt(1, 10_000_000));
            $suggested = SuggestedRetailPrice::fromCost($cost, Rate::of($this->random->getInt(-999_999, 3_000_000)));
            $ended = $suggested->ended($ending);

            self::assertGreaterThanOrEqual($suggested->price->minorUnits, $ended->price->minorUnits);
            self::assertGreaterThanOrEqual($cost->minorUnits, $ended->price->minorUnits);
            self::assertLessThan(max($suggested->price->minorUnits, $cost->minorUnits) + $step, $ended->price->minorUnits);
            self::assertSame($suggested->price->minorUnits, $ended->unendedPrice->minorUnits);
        }
    }

    public function testASuggestedPriceFromACostIsTheSmallestThatReachesItsMarkup(): void
    {
        for ($i = 0; $i < self::CASES; ++$i) {
            $cost = $this->random->getInt(1, 10_000_000);
            $markup = $this->random->getInt(-999_999, 3_000_000);
            $price = SuggestedRetailPrice::fromCost($this->cad($cost), Rate::of($markup))->price->minorUnits;
            $exact = $cost * ($markup + 1_000_000);

            self::assertGreaterThanOrEqual($exact, $price * 1_000_000);
            self::assertLessThan($exact, ($price - 1) * 1_000_000);
        }
    }

    public function testAComparisonAddsUpAndItsRateHasTheSignOfItsSide(): void
    {
        for ($i = 0; $i < self::CASES; ++$i) {
            $suggested = $this->random->getInt(1, 10_000_000);
            $selling = $this->random->getInt(0, 20_000_000);
            $comparison = RetailPriceComparison::of($this->cad($selling), $this->cad($suggested));

            self::assertSame($selling, $suggested + $comparison->difference()->minorUnits);
            self::assertSame($selling <=> $suggested, $comparison->rate(RoundingMode::AwayFromZero)->partsPerMillion <=> 0);
            self::assertSame(max(0, -$comparison->rate()->partsPerMillion), $comparison->rateBelow()->partsPerMillion);
        }
    }

    public function testAWholesaleUnitPriceNeverRisesWithTheQuantity(): void
    {
        for ($i = 0; $i < 500; ++$i) {
            $cost = $this->cad($this->random->getInt(1, 1_000_000));
            $markup = $this->random->getInt(0, 2_000_000);
            $list = WholesalePriceList::fromCost($cost, Rate::of($markup));
            $quantity = 1;

            for ($tier = 0; $tier < 5; ++$tier) {
                $quantity += $this->random->getInt(1, 50);
                $markup = $this->random->getInt(-999_999, $markup);
                $list = $list->fromMarkup($quantity, Rate::of($markup));
            }

            $previous = \PHP_INT_MAX;

            for ($units = 1; $units <= $quantity + 10; ++$units) {
                $unitPrice = $list->unitPriceFor($units)->minorUnits;

                self::assertLessThanOrEqual($previous, $unitPrice);
                $previous = $unitPrice;
            }
        }
    }
}
