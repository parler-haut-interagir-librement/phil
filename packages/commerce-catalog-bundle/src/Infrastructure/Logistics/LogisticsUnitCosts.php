<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\Logistics;

use Override;
use Phil\CommerceCatalogBundle\Application\Contract\UnitCosts;
use Phil\CommerceLogisticsBundle\Application\Contract\InventoryValuation;
use Phil\Money\Money;

/**
 * `UnitCosts` on the costing of `phil/commerce-logistics-bundle`: the unit cost of the shop's own units, at every
 * location, units left on consignment never counted.
 */
final readonly class LogisticsUnitCosts implements UnitCosts
{
    public function __construct(
        private InventoryValuation $valuation,
    ) {
    }

    #[Override]
    public function unitCost(string $sku): ?Money
    {
        return $this->valuation->unitCost($sku);
    }
}
