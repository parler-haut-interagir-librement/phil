<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceLogisticsBundle\Application\Contract\ShipmentForcing;

/**
 * Nobody forces a shipment until the application says who may, by aliasing its own `ShipmentForcing`.
 */
final readonly class DenyShipmentForcing implements ShipmentForcing
{
    #[Override]
    public function allows(Order $order, string $actor): bool
    {
        return false;
    }
}
