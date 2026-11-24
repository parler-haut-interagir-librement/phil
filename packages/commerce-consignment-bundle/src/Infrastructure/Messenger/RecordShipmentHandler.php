<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Messenger;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceConsignmentBundle\Application\Message\RecordShipment;
use Phil\CommerceConsignmentBundle\Application\ShippedOrders;
use Psr\Clock\ClockInterface;

/**
 * Writes what an order shipped, in a worker: the order read again by its number, an order no longer there skipped. Run
 * it on a bus with Doctrine's transaction middleware, which commits the ledger once the handler returns.
 */
final readonly class RecordShipmentHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ShippedOrders $shipped,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordShipment $message): void
    {
        $order = $this->orders->find($message->orderNumber);

        if ($order instanceof Order) {
            $this->shipped->record($order, $this->clock->now());
        }
    }
}
