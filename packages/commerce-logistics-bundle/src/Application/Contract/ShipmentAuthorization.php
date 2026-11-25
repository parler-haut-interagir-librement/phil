<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Contract;

use Phil\CommerceBundle\Domain\Model\Order;

/**
 * Whether a placed order may leave now. `Shipments::ship()` asks every authorization and ships when one of them says
 * yes; `PaidShipmentAuthorization`, the bundle's own, says yes once the payments cover what the order is due.
 *
 * An authorization only answers: it changes nothing, charges nothing and records nothing. A service implementing it is
 * tagged `phil_commerce_logistics.shipment_authorization` by autoconfiguration.
 */
interface ShipmentAuthorization
{
    public function authorizes(Order $order): bool;
}
