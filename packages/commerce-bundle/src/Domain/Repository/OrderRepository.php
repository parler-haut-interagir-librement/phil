<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Repository;

use Phil\CommerceBundle\Domain\Model\Order;

/**
 * Finds and stores orders. `Infrastructure\Doctrine\DoctrineOrderRepository` is the Doctrine adapter.
 *
 * Adding an order schedules it to be stored; the application commits its unit of work, as it does for its own
 * entities. A change to an order found here is stored with that same commit.
 */
interface OrderRepository
{
    public function find(string $number): ?Order;

    public function add(Order $order): void;
}
