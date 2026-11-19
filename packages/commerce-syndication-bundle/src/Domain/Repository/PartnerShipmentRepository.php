<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Repository;

use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;

interface PartnerShipmentRepository
{
    public function findShipment(string $partner, string $shipment): ?PartnerShipment;

    public function addShipment(PartnerShipment $shipment): void;

    /**
     * @return list<PartnerShipment> the shipments of the order with the partner, oldest first
     */
    public function shipmentsOf(string $partner, string $orderNumber): array;
}
