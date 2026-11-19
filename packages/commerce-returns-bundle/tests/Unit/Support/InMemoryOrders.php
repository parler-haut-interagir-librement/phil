<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;

/**
 * The core's orders, in memory.
 */
final class InMemoryOrders implements OrderRepository
{
    /** @var array<string, Order> */
    public array $orders = [];

    #[Override]
    public function find(string $number): ?Order
    {
        return $this->orders[$number] ?? null;
    }

    #[Override]
    public function add(Order $order): void
    {
        $this->orders[$order->number] = $order;
    }
}
