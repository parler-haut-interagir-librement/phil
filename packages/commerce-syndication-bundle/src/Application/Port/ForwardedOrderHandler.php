<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application\Port;

use Phil\CommerceSyndicationBundle\Application\ForwardedOrder;

/**
 * What the application does with an order a reseller forwarded: prepare its shipment, most often.
 *
 * A service implementing it is tagged `phil_commerce_syndication.forwarded_order_handler` by autoconfiguration.
 * It runs once per order, in the transaction that records the message.
 */
interface ForwardedOrderHandler
{
    public function handle(ForwardedOrder $order): void;
}
