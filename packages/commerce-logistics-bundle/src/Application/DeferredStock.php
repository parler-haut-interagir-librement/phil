<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Contract\StockCommitments;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Psr\Clock\ClockInterface;

/**
 * The core's `StockCommitments` for sales paid later — preorders, installments, layaways —: committing the stock of a
 * deferred sale reserves its order, releasing it gives the reservations back. Reserving or releasing twice changes
 * nothing, as with `Stock`.
 */
final readonly class DeferredStock implements StockCommitments
{
    public function __construct(
        private OrderRepository $orders,
        private Stock $stock,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function commit(string $orderNumber): void
    {
        $order = $this->orders->find($orderNumber);

        if (!$order instanceof Order) {
            throw StockRefused::unknownOrder($orderNumber);
        }

        $this->stock->reserve($order, $this->clock->now());
    }

    #[Override]
    public function release(string $orderNumber): void
    {
        $this->stock->release($orderNumber, $this->clock->now());
    }
}
