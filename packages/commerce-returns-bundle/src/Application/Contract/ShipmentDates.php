<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application\Contract;

use DateTimeImmutable;

/**
 * Tells when an order last shipped, for a return window counted from the shipment.
 * `Infrastructure\Logistics\LogisticsShipmentDates` reads the shipments of `phil/commerce-logistics-bundle`.
 */
interface ShipmentDates
{
    /**
     * The moment of the order's last shipment; `null` when nothing of it shipped.
     */
    public function lastShippedAt(string $orderNumber): ?DateTimeImmutable;
}
