<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Infrastructure\Logistics;

use DateTimeImmutable;
use Override;
use Phil\CommerceLogisticsBundle\Domain\Repository\ShipmentRepository;
use Phil\CommerceReturnsBundle\Application\Contract\ShipmentDates;

/**
 * `ShipmentDates` on `phil/commerce-logistics-bundle`: the latest of the order's shipments.
 */
final readonly class LogisticsShipmentDates implements ShipmentDates
{
    public function __construct(
        private ShipmentRepository $shipments,
    ) {
    }

    #[Override]
    public function lastShippedAt(string $orderNumber): ?DateTimeImmutable
    {
        $last = null;

        foreach ($this->shipments->shipmentsOf($orderNumber) as $shipment) {
            if (!$last instanceof DateTimeImmutable || $shipment->shippedAt > $last) {
                $last = $shipment->shippedAt;
            }
        }

        return $last;
    }
}
