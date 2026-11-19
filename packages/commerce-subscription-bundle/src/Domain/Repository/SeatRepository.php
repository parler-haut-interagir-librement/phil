<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Repository;

use Phil\CommerceSubscriptionBundle\Domain\Model\Seat;

/**
 * Finds and stores the seats of organizations' subscriptions. `Infrastructure\Doctrine\DoctrineSeatRepository` is the
 * Doctrine adapter.
 */
interface SeatRepository
{
    public function find(string $subscriptionId, string $member): ?Seat;

    /**
     * @return list<Seat> the seats of the subscription, earliest assigned first, then by member; at most `$limit`
     */
    public function holders(string $subscriptionId, ?int $limit = null): array;

    public function count(string $subscriptionId): int;

    /**
     * Whether the member holds one of the first `$seats` seats of the subscription, earliest assigned first.
     */
    public function holdsSeat(string $subscriptionId, string $member, int $seats): bool;

    public function add(Seat $seat): void;

    public function remove(Seat $seat): void;
}
