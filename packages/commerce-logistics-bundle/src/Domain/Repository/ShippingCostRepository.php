<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\ShippingCost;

interface ShippingCostRepository
{
    public function addCost(ShippingCost $cost): void;

    public function costOf(string $shipment): ?ShippingCost;

    /**
     * @return list<ShippingCost> the costs of the order's shipments, oldest first
     */
    public function costsOf(string $orderNumber): array;
}
