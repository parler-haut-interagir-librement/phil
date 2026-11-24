<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application\Contract;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;

/**
 * The frozen copies of the delivery addresses, one per order, and the erasure of the delivery addresses kept on the
 * subscriptions and on those copies.
 *
 * `add()` and `find()` work in the unit of work the application commits; `erase()` and `purge()` write at once, so
 * that an erasure or a purge cut short leaves what it did done. Both are idempotent: run again, they find nothing
 * left and answer 0.
 */
interface DeliveryAddressRepository
{
    public function find(string $orderNumber): ?OrderAddress;

    public function add(OrderAddress $copy): void;

    /**
     * @return list<OrderAddress> the subscriber's copies, oldest first, the first `$limit`
     */
    public function ofSubscriber(string $subscriber, int $limit): array;

    /**
     * Forgets the delivery address of each of the subscriber's subscriptions, and deletes the copies of their orders.
     *
     * @return int how many addresses and copies were erased
     */
    public function erase(string $subscriber): int;

    /**
     * Forgets the delivery address of at most `$limit` subscriptions ended before `$cutoff` and shipped to before it,
     * if ever, and deletes at most `$limit` copies kept since before it.
     *
     * @return array{addresses: int, copies: int} how many were erased
     */
    public function purge(DateTimeImmutable $cutoff, int $limit): array;
}
