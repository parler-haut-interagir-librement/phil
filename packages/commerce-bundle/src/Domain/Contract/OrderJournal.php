<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Contract;

use Phil\CommerceBundle\Domain\Model\OrderStateChange;

/**
 * The journal of the orders' moves. `Infrastructure\Doctrine\DoctrineOrderJournal` is the Doctrine adapter.
 */
interface OrderJournal
{
    public function record(OrderStateChange $change): void;

    /**
     * @return list<OrderStateChange> the order's moves, oldest first, at most `$limit`
     */
    public function movesOf(string $orderNumber, int $limit = 100): array;
}
