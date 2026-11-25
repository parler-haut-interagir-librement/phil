<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Contract;

use Phil\CommerceBundle\Domain\Model\Order;

/**
 * Whether someone may ship an order that no `ShipmentAuthorization` lets leave: the application answers, usually
 * through its security voters. `DenyShipmentForcing`, the default, refuses everyone.
 */
interface ShipmentForcing
{
    /**
     * @param string $actor the stable identifier of the person who forces the shipment
     */
    public function allows(Order $order, string $actor): bool;
}
