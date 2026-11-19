<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application\Port;

use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;

/**
 * What a reseller does when its supplier shipped an order it forwarded: mark the order shipped, tell the buyer the
 * tracking number.
 *
 * A service implementing it is tagged `phil_commerce_syndication.shipment_handler` by autoconfiguration. It runs once
 * per shipment, in the transaction that records the message.
 */
interface PartnerShipmentHandler
{
    public function shipped(PartnerShipment $shipment): void;
}
