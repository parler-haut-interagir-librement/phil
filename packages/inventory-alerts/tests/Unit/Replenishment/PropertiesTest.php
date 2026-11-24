<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Unit\Replenishment;

use Phil\InventoryAlerts\Internal\Check;
use Phil\InventoryAlerts\Replenishment\EconomicOrderQuantity;
use Phil\InventoryAlerts\Replenishment\ReorderPoint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Properties of the reorder point and of the economic order quantity, over inputs drawn from a fixed seed.
 */
#[CoversClass(ReorderPoint::class)]
#[CoversClass(EconomicOrderQuantity::class)]
#[CoversClass(Check::class)]
final class PropertiesTest extends TestCase
{
    private const int RUNS = 500;

    public function testTheReorderPointCoversTheLeadTimeDemandByLessThanAUnit(): void
    {
        $random = new Randomizer(new Mt19937(57));

        for ($run = 0; $run < self::RUNS; ++$run) {
            $demand = $random->getFloat(0, 10_000);
            $leadTime = $random->getFloat(0, 365);
            $safety = $random->getInt(0, 100_000);
            $point = ReorderPoint::of($demand, $leadTime, $safety);
            $leadTimeDemand = $demand * $leadTime;

            self::assertGreaterThanOrEqual($leadTimeDemand - 1e-6, (float) ($point - $safety));
            self::assertLessThan($leadTimeDemand + 1.0, (float) ($point - $safety));
        }
    }

    public function testTheReorderPointRisesWithTheDemandTheLeadTimeAndTheSafetyStock(): void
    {
        $random = new Randomizer(new Mt19937(5_757));

        for ($run = 0; $run < self::RUNS; ++$run) {
            $demand = $random->getFloat(0, 1_000);
            $leadTime = $random->getFloat(0, 120);
            $safety = $random->getInt(0, 1_000);
            $point = ReorderPoint::of($demand, $leadTime, $safety);

            self::assertGreaterThanOrEqual($point, ReorderPoint::of($demand + $random->getFloat(0, 100), $leadTime, $safety));
            self::assertGreaterThanOrEqual($point, ReorderPoint::of($demand, $leadTime + $random->getFloat(0, 30), $safety));
            self::assertSame($point + 7, ReorderPoint::of($demand, $leadTime, $safety + 7));
        }
    }

    public function testTheEconomicOrderQuantityCostsNoMoreThanAnyOtherQuantity(): void
    {
        $random = new Randomizer(new Mt19937(2_026));

        for ($run = 0; $run < self::RUNS; ++$run) {
            $demand = $random->getFloat(1, 1_000_000);
            $orderCost = $random->getFloat(0.01, 1_000);
            $holdingCost = $random->getFloat(0.01, 100);
            $eoq = new EconomicOrderQuantity($demand, $orderCost, $holdingCost);
            $exact = $eoq->exact();
            $cost = static fn (float $quantity): float => $demand / $quantity * $orderCost + $quantity / 2 * $holdingCost;

            // At the optimum, ordering and holding cost the same.
            self::assertEqualsWithDelta($demand / $exact * $orderCost, $exact / 2 * $holdingCost, 1e-6 * $cost($exact));
            $other = $exact * $random->getFloat(0.1, 10);
            self::assertLessThanOrEqual($cost($other) * (1 + 1e-12), $cost($exact));
        }
    }

    public function testTheEconomicOrderQuantityGrowsAsTheSquareRootOfTheDemand(): void
    {
        $random = new Randomizer(new Mt19937(42));

        for ($run = 0; $run < self::RUNS; ++$run) {
            $demand = $random->getFloat(1, 1_000_000);
            $orderCost = $random->getFloat(0.01, 1_000);
            $holdingCost = $random->getFloat(0.01, 100);
            $exact = new EconomicOrderQuantity($demand, $orderCost, $holdingCost)->exact();

            self::assertEqualsWithDelta(2 * $exact, new EconomicOrderQuantity(4 * $demand, $orderCost, $holdingCost)->exact(), 1e-9 * $exact);
        }
    }

    public function testTheWholeQuantityIsTheSmallestMultipleOfThePackAtOrAboveTheExactOne(): void
    {
        $random = new Randomizer(new Mt19937(7));

        for ($run = 0; $run < self::RUNS; ++$run) {
            $eoq = new EconomicOrderQuantity($random->getFloat(1, 1_000_000), $random->getFloat(0.01, 1_000), $random->getFloat(0.01, 100));
            $pack = $random->getInt(1, 48);
            $quantity = $eoq->quantity($pack);

            self::assertSame(0, $quantity % $pack);
            self::assertGreaterThanOrEqual($eoq->exact() - 1e-6, (float) $quantity);
            self::assertLessThan($eoq->exact() + $pack, (float) $quantity);
        }
    }
}
