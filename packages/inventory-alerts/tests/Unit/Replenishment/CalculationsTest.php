<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Unit\Replenishment;

use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;
use Phil\InventoryAlerts\Replenishment\DemandHistory;
use Phil\InventoryAlerts\Replenishment\EconomicOrderQuantity;
use Phil\InventoryAlerts\Replenishment\ReorderPoint;
use Phil\InventoryAlerts\Replenishment\SafetyStock;
use Phil\InventoryAlerts\Replenishment\ServiceLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The calculations of replenishment against values worked out by hand.
 */
#[CoversClass(DemandHistory::class)]
#[CoversClass(ServiceLevel::class)]
#[CoversClass(SafetyStock::class)]
#[CoversClass(ReorderPoint::class)]
#[CoversClass(EconomicOrderQuantity::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidInput::class)]
final class CalculationsTest extends TestCase
{
    public function testADemandHistoryGivesItsMeanSampleDeviationAndMaximum(): void
    {
        // Mean 25 / 5 = 5; squared deviations 1 + 1 + 0 + 4 + 4 = 10; sample variance 10 / 4 = 2.5.
        $history = new DemandHistory([4, 6, 5, 7.0, 3]);

        self::assertSame([4.0, 6.0, 5.0, 7.0, 3.0], $history->periods);
        self::assertSame(5.0, $history->mean());
        self::assertEqualsWithDelta(sqrt(2.5), $history->standardDeviation(), 1e-12);
        self::assertSame(7.0, $history->max());
    }

    public function testASinglePeriodHasNoDeviation(): void
    {
        self::assertSame(0.0, new DemandHistory([8])->standardDeviation());
        self::assertSame(8.0, new DemandHistory([8])->mean());
    }

    public function testATwoPeriodHistoryDividesByOne(): void
    {
        // Mean 3; squared deviations 1 + 1 = 2; sample variance 2 / 1.
        self::assertEqualsWithDelta(sqrt(2.0), new DemandHistory([2, 4])->standardDeviation(), 1e-12);
    }

    public function testAHistoryHasOneTo3660Periods(): void
    {
        self::assertCount(3_660, new DemandHistory(array_fill(0, 3_660, 1))->periods);

        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The demand history must have 1 to 3,660 periods.');

        new DemandHistory(array_fill(0, 3_661, 1));
    }

    public function testAnEmptyHistoryIsRefused(): void
    {
        $this->expectException(InvalidInput::class);

        new DemandHistory([]);
    }

    public function testANegativeDemandIsRefused(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The demand of a period must be a finite number from 0 to 1,000,000,000,000.');

        new DemandHistory([1, -1]);
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function quantiles(): iterable
    {
        yield 'median' => [0.5, 0.0];
        yield 'one deviation' => [0.84134474606854, 1.0];
        yield '90 %' => [0.9, 1.2815516];
        yield '95 %' => [0.95, 1.6448536];
        yield '97.5 %, central region' => [0.975, 1.9599640];
        yield 'the edge of the central region' => [0.97575, 1.9729611];
        yield '98 %, tail' => [0.98, 2.0537489];
        yield '99 %' => [0.99, 2.3263479];
        yield '99.99 %' => [0.9999, 3.7190165];
    }

    #[DataProvider('quantiles')]
    public function testAServiceLevelGivesTheQuantileOfTheNormalDistribution(float $probability, float $z): void
    {
        $level = ServiceLevel::of($probability);

        self::assertSame($probability, $level->probability);
        self::assertEqualsWithDelta($z, $level->z(), 1e-7);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function refusedLevels(): iterable
    {
        yield 'under half' => [0.4999];
        yield 'above 99.99 %' => [0.99991];
        yield 'one' => [1.0];
        yield 'not a number' => [\NAN];
    }

    #[DataProvider('refusedLevels')]
    public function testAServiceLevelOutsideItsRangeIsRefused(float $probability): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The service level must be from 0.5 to 0.9999.');

        ServiceLevel::of($probability);
    }

    public function testTheSimpleSafetyStockIsTheWorstCaseLessTheAverageCase(): void
    {
        // 20 × 10 − 12 × 7 = 200 − 84.
        self::assertSame(116, SafetyStock::simple(20, 10, 12, 7));
        // 2.5 × 3 − 1 × 4 = 3.5, rounded up.
        self::assertSame(4, SafetyStock::simple(2.5, 3, 1, 4));
        // A worst case under the average case keeps no safety stock.
        self::assertSame(0, SafetyStock::simple(1, 1, 2, 2));
    }

    public function testTheSimpleSafetyStockRefusesALeadTimeOverTenYears(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The maximum lead time must be a finite number from 0 to 3,650.');

        SafetyStock::simple(1, 3_651, 1, 1);
    }

    public function testAQuantityIsRoundedToSixDecimalsBeforeItIsRoundedUp(): void
    {
        // A tenth of a millionth over a whole number is the rounding of floats, not a unit more.
        self::assertSame(2, SafetyStock::simple(2.0000001, 1, 0, 0));
        // Four millionths over it is a real share of a unit.
        self::assertSame(3, SafetyStock::simple(2.000004, 1, 0, 0));
    }

    /**
     * @return iterable<string, array{float, float, float, float, string}>
     */
    public static function refusedSimpleInputs(): iterable
    {
        yield 'negative maximum demand' => [-1, 1, 0, 0, 'The maximum daily demand must be a finite number from 0 to 1,000,000,000,000.'];
        yield 'negative average demand' => [1, 1, -1, 0, 'The average daily demand must be a finite number from 0 to 1,000,000,000,000.'];
        yield 'negative average lead time' => [1, 1, 0, -1, 'The average lead time must be a finite number from 0 to 3,650.'];
    }

    #[DataProvider('refusedSimpleInputs')]
    public function testTheSimpleSafetyStockRefusesInputsOutOfRange(float $maxDemand, float $maxLeadTime, float $averageDemand, float $averageLeadTime, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        SafetyStock::simple($maxDemand, $maxLeadTime, $averageDemand, $averageLeadTime);
    }

    public function testTheStatisticalSafetyStockDefaultsToAFixedLeadTimeAndNoAverageDemand(): void
    {
        $level = ServiceLevel::of(0.95);

        // No average demand: the deviation of the lead time adds nothing, 1.6448536 × 10 × √9.
        self::assertSame(50, SafetyStock::statistical($level, 10, 9, leadTimeStandardDeviation: 20));
        // A lead time that does not vary: the average demand adds nothing.
        self::assertSame(50, SafetyStock::statistical($level, 10, 9, averageDailyDemand: 20));
    }

    public function testTheTailApproximationStartsAbove97575Percent(): void
    {
        // At 97.575 % itself, the central approximation still answers; the tail one gives 1.9729610535.
        self::assertSame(1.9729610490848712, ServiceLevel::of(0.97575)->z());
    }

    public function testTheStatisticalSafetyStockScalesTheDeviationByTheLeadTime(): void
    {
        $level = ServiceLevel::of(0.95);

        // 1.6448536 × 10 × √9 = 49.35, rounded up.
        self::assertSame(50, SafetyStock::statistical($level, 10, 9));
        // √(9 × 10² + 20² × 2²) = √2500 = 50; × 1.6448536 = 82.24, rounded up.
        self::assertSame(83, SafetyStock::statistical($level, 10, 9, 20, 2));
        // A lead time that varies with an average demand of 0 adds nothing.
        self::assertSame(50, SafetyStock::statistical($level, 10, 9, 0, 5));
        self::assertSame(0, SafetyStock::statistical(ServiceLevel::of(0.5), 10, 9));
    }

    public function testTheSafetyStockOfAHistoryUsesItsMeanAndDeviation(): void
    {
        $history = new DemandHistory([4, 6, 5, 7, 3]);
        $level = ServiceLevel::of(0.95);

        // √(4 × 2.5) = 3.162; × 1.6448536 = 5.20, rounded up.
        self::assertSame(6, SafetyStock::fromHistory($history, $level, 4));
        // √(4 × 2.5 + 5² × 1²) = √35 = 5.916; × 1.6448536 = 9.73, rounded up.
        self::assertSame(10, SafetyStock::fromHistory($history, $level, 4, 1));
    }

    /**
     * @return iterable<string, array{float, float, float, float}>
     */
    public static function refusedStatisticalInputs(): iterable
    {
        yield 'negative deviation' => [-1, 9, 0, 0];
        yield 'negative lead time' => [10, -1, 0, 0];
        yield 'infinite demand' => [10, 9, \INF, 0];
        yield 'negative lead time deviation' => [10, 9, 0, -1];
    }

    #[DataProvider('refusedStatisticalInputs')]
    public function testTheStatisticalSafetyStockRefusesInputsOutOfRange(float $deviation, float $leadTime, float $demand, float $leadTimeDeviation): void
    {
        $this->expectException(InvalidInput::class);

        SafetyStock::statistical(ServiceLevel::of(0.95), $deviation, $leadTime, $demand, $leadTimeDeviation);
    }

    public function testTheReorderPointIsTheLeadTimeDemandPlusTheSafetyStock(): void
    {
        // 12 × 7 + 116.
        self::assertSame(200, ReorderPoint::of(12, 7, 116));
        // 2.5 × 3 = 7.5, rounded up.
        self::assertSame(8, ReorderPoint::of(2.5, 3));
        // 0.1 × 30 is 3.0000000000000004 in floats: three units, not four.
        self::assertSame(3, ReorderPoint::of(0.1, 30));
        self::assertSame(5, ReorderPoint::of(0, 30, 5));
    }

    public function testTheReorderPointOfAHistoryAddsItsSafetyStock(): void
    {
        // 5 × 4 + 6.
        self::assertSame(26, ReorderPoint::fromHistory(new DemandHistory([4, 6, 5, 7, 3]), 4, ServiceLevel::of(0.95)));
        // 5 × 4 + 10.
        self::assertSame(30, ReorderPoint::fromHistory(new DemandHistory([4, 6, 5, 7, 3]), 4, ServiceLevel::of(0.95), 1));
    }

    public function testTheReorderPointRefusesANegativeSafetyStock(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The safety stock must be a whole number from 0 to 1,000,000,000,000.');

        ReorderPoint::of(1, 1, -1);
    }

    public function testTheReorderPointRefusesAnInfiniteDemand(): void
    {
        $this->expectException(InvalidInput::class);

        ReorderPoint::of(\INF, 1);
    }

    public function testTheReorderPointRefusesALeadTimeOverTenYears(): void
    {
        $this->expectException(InvalidInput::class);

        ReorderPoint::of(1, 3_650.5);
    }

    public function testTheEconomicOrderQuantityBalancesOrderingAndHolding(): void
    {
        // √(2 × 1200 × 50 / 3) = √40000.
        $eoq = new EconomicOrderQuantity(1_200, 50, 3);

        self::assertSame(200.0, $eoq->exact());
        self::assertSame(200, $eoq->quantity());
        // 200 / 12 = 16.7 packs, rounded up to 17.
        self::assertSame(204, $eoq->quantity(12));
        self::assertSame(200, $eoq->quantity(200));
        // √(2 × 500 × 20 / 4) = √5000 = 70.71, rounded up.
        self::assertSame(71, new EconomicOrderQuantity(500, 20, 4)->quantity());
        self::assertSame(1_200.0, $eoq->annualDemand);
        self::assertSame(50.0, $eoq->orderCost);
        self::assertSame(3.0, $eoq->annualHoldingCost);
    }

    public function testTheEconomicOrderQuantityRefusesAPackOfNothing(): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains('The pack size must be a whole number from 1 to 1,000,000,000,000.');

        new EconomicOrderQuantity(1_200, 50, 3)->quantity(0);
    }

    /**
     * @return iterable<string, array{float, float, float, string}>
     */
    public static function refusedEoqInputs(): iterable
    {
        yield 'no demand' => [0, 50, 3, 'The annual demand must be a finite number above 0 and at most 1,000,000,000,000.'];
        yield 'free orders' => [1_200, 0, 3, 'The cost of an order must be a finite number above 0 and at most 1,000,000,000,000.'];
        yield 'free holding' => [1_200, 50, 0, 'The annual cost of holding a unit must be a finite number above 0 and at most 1,000,000,000,000.'];
        yield 'demand above the bound' => [1.1e12, 50, 3, 'The annual demand must be a finite number above 0 and at most 1,000,000,000,000.'];
        yield 'quantity above the bound' => [1e12, 1e12, 1, 'The economic order quantity must not exceed 1,000,000,000,000 units.'];
    }

    #[DataProvider('refusedEoqInputs')]
    public function testTheEconomicOrderQuantityRefusesInputsOutOfRange(float $demand, float $orderCost, float $holdingCost, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        new EconomicOrderQuantity($demand, $orderCost, $holdingCost);
    }

    public function testTheBoundsThemselvesAreAccepted(): void
    {
        // √(2 × 1e12 × 0.5 / 1) = √1e12: the largest demand.
        self::assertSame(1_000_000, new EconomicOrderQuantity(1e12, 0.5, 1)->quantity());
        // √(2 × 5e11 × 1e12 / 1) = 1e12: the largest quantity.
        self::assertSame(1_000_000_000_000, new EconomicOrderQuantity(5e11, 1e12, 1)->quantity());
        self::assertSame(0, ReorderPoint::of(0, 3_650));
        self::assertSame(1_000_000_000_000, ReorderPoint::of(0, 0, 1_000_000_000_000));
    }
}
