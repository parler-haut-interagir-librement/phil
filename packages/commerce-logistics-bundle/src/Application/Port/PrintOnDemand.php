<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Port;

/**
 * Sends units of an order to be printed rather than taken from stock: the convention items of an order placed online
 * while a convention is near, or while their stock is short.
 *
 * A service implementing it is tagged `phil_commerce_logistics.print_on_demand` by autoconfiguration. It is called
 * once per order and SKU, in the transaction that reserves the order; an implementation that talks to a printer
 * queues a message carrying the order number and the SKU, and reads the rest from its own store.
 */
interface PrintOnDemand
{
    public function print(string $orderNumber, string $sku, int $quantity): void;
}
