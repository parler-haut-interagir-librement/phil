<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Repository;

use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;

interface ShipmentRepository
{
    public function addShipment(Shipment $shipment): void;

    /**
     * @return list<Shipment> the order's shipments, oldest first
     */
    public function shipmentsOf(string $orderNumber): array;
}
