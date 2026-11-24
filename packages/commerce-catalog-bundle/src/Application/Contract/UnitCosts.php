<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application\Contract;

use Phil\Money\Money;

/**
 * What one unit of a SKU costs the shop, from whatever values its stock: `Infrastructure\Logistics\LogisticsUnitCosts`
 * reads the costing of `phil/commerce-logistics-bundle`; an application points the alias of this interface at its own.
 */
interface UnitCosts
{
    /**
     * @return Money|null the cost of one unit now; null when it is not known
     */
    public function unitCost(string $sku): ?Money;
}
