<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\CommerceLogisticsBundle\Domain\Contract\CostRepository;
use Phil\CommerceLogisticsBundle\Domain\Cost\CostShare;
use Phil\CommerceLogisticsBundle\Domain\Model\CostEntry;
use Phil\CommerceLogisticsBundle\Domain\Model\CostLayer;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\SaleCost;
use Phil\CommerceLogisticsBundle\Domain\ValueObject\ValuedStock;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * `InventoryValuation` on the cost layers and the cost journal: the unit cost from the layers, everything else summed
 * by the database. With costing disabled, the journal is empty and every answer is too.
 */
final readonly class InventoryValue implements InventoryValuation
{
    /** The most entries one page gives. */
    public const int MAX_PAGE = 1_000;

    public function __construct(
        private CostRepository $costs,
    ) {
    }

    #[Override]
    public function unitCost(string $sku): ?Money
    {
        $layers = $this->costs->shopLayersOf($sku);
        $units = array_sum(array_map(static fn (CostLayer $layer): int => $layer->units(), $layers));

        if ([] !== $layers && $units > 0) {
            return CostShare::of(Money::sum(...array_map(static fn (CostLayer $layer): Money => $layer->value(), $layers)), 1, $units);
        }

        $receipt = $this->costs->latestCostedReceipt($sku);

        return $receipt instanceof CostEntry ? CostShare::of($receipt->value(), 1, $receipt->costedUnits()) : null;
    }

    #[Override]
    public function valuedStock(DateTimeImmutable $at): array
    {
        return array_map(
            static fn (array $row): ValuedStock => new ValuedStock($row['sku'], $row['location'], $row['units'], Money::of($row['value'], Currency::of($row['currency']))),
            $this->costs->valuation($at),
        );
    }

    #[Override]
    public function costOfSales(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return array_map(
            static fn (array $row): SaleCost => new SaleCost($row['reference'], $row['sku'], -$row['units'], -$row['uncosted'], Money::of(-$row['value'], Currency::of($row['currency']))),
            $this->costs->salesCost($from, $until),
        );
    }

    #[Override]
    public function entries(DateTimeImmutable $from, DateTimeImmutable $until, array $kinds = [], ?string $after = null, int $limit = 500): array
    {
        if ($limit < 1 || $limit > self::MAX_PAGE) {
            throw InvalidValue::because(\sprintf('A page of the cost journal holds 1 to %d entries; %d is not.', self::MAX_PAGE, $limit));
        }

        return $this->costs->entries($from, $until, $kinds, $after, $limit);
    }
}
