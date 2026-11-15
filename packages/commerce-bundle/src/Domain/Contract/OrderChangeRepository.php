<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Contract;

use Phil\CommerceBundle\Domain\Model\OrderChange;

/**
 * Finds and stores the changes of placed orders. `Infrastructure\Doctrine\DoctrineOrderChangeRepository` is the
 * Doctrine adapter.
 */
interface OrderChangeRepository
{
    public function findOrderChange(string $id): ?OrderChange;

    /**
     * @return list<OrderChange> the order's changes, oldest first
     */
    public function changesOf(string $orderNumber): array;

    public function addOrderChange(OrderChange $change): void;
}
