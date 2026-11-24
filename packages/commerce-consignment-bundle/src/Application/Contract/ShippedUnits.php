<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application\Contract;

use Phil\CommerceConsignmentBundle\Application\ShippedQuantity;

/**
 * What an order took off the shelves, SKU by SKU and location by location. `Infrastructure\Logistics\LogisticsShippedUnits`
 * reads the reservations of `phil/commerce-logistics-bundle`.
 */
interface ShippedUnits
{
    /**
     * @return list<ShippedQuantity> one per SKU and location the order shipped units from
     */
    public function shipped(string $orderNumber): array;
}
