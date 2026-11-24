<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Logistics;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceConsignmentBundle\Application\ShippedOrders;
use Phil\CommerceLogisticsBundle\Domain\Event\UnitsShipped;

/**
 * Writes to the ledger what an order shipped as soon as `phil/commerce-logistics-bundle` announces it, at the moment
 * the units left. The logistics module dispatches `UnitsShipped` before anything is committed: the sales join the
 * shipment's transaction, written by the same flush. An order no longer there is skipped.
 */
final readonly class UnitsShippedListener
{
    public function __construct(
        private OrderRepository $orders,
        private ShippedOrders $shipped,
    ) {
    }

    public function __invoke(UnitsShipped $event): void
    {
        $order = $this->orders->find($event->orderNumber);

        if ($order instanceof Order) {
            $this->shipped->record($order, $event->occurredAt());
        }
    }
}
