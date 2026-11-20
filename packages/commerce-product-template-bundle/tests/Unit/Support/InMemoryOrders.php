<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;

final class InMemoryOrders implements OrderRepository
{
    /** @var array<string, Order> */
    private array $orders = [];

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
