<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Infrastructure\Logistics;

use DateTimeImmutable;
use Override;
use Phil\CommerceCatalogBundle\Infrastructure\Logistics\LogisticsUnitCosts;
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogisticsUnitCosts::class)]
final class LogisticsUnitCostsTest extends TestCase
{
    public function testItReadsTheUnitCostOfTheLogisticsCosting(): void
    {
        $valuation = new class implements InventoryValuation {
            #[Override]
            public function unitCost(string $sku): ?Money
            {
                return 'DICE' === $sku ? Money::of(333, Currency::of('CAD')) : null;
            }

            #[Override]
            public function valuedStock(DateTimeImmutable $at): array
            {
                return [];
            }

            #[Override]
            public function costOfSales(DateTimeImmutable $from, DateTimeImmutable $until): array
            {
                return [];
            }

            #[Override]
            public function entries(DateTimeImmutable $from, DateTimeImmutable $until, array $kinds = [], ?string $after = null, int $limit = 500): array
            {
                return [];
            }
        };
        $costs = new LogisticsUnitCosts($valuation);

        self::assertSame(333, $costs->unitCost('DICE')?->minorUnits);
        self::assertNull($costs->unitCost('BOOK'));
    }
}
