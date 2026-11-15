<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;

/**
 * Finds the orders nobody paid in time, and deletes the expired ones once kept long enough.
 * `Infrastructure\Doctrine\DoctrineOrderRepository` is the Doctrine adapter.
 */
interface ExpiringOrders
{
    /**
     * The placed orders placed before the moment that hold no payment that counts and are no deferred sale, oldest
     * first, at most `$limit`.
     *
     * @param list<string>|null $channels the channels to read, or `null` for the orders of none of `$except`
     * @param list<string>      $except   with `$channels` at `null`, the channels left out
     *
     * @return list<Order>
     */
    public function unpaidPlacedBefore(?array $channels, array $except, DateTimeImmutable $before, int $limit): array;

    /**
     * The order numbers of the orders that expired before the moment, never paid and without a receipt, oldest first,
     * at most `$limit`.
     *
     * @param list<string>|null $channels as for `unpaidPlacedBefore()`
     * @param list<string>      $except
     *
     * @return list<string>
     */
    public function expiredBefore(?array $channels, array $except, DateTimeImmutable $before, int $limit): array;

    /**
     * Deletes an expired order with what belongs to it alone: its payments that captured nothing, its payment links,
     * its changes and its journal.
     */
    public function deleteExpired(string $orderNumber): void;
}
