<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;

/**
 * Finds and stores subscriptions. `Infrastructure\Doctrine\DoctrineSubscriptionRepository` is the Doctrine adapter.
 */
interface SubscriptionRepository
{
    public function find(string $id): ?Subscription;

    /**
     * @return list<Subscription> every subscription of the subscriber, oldest first
     */
    public function forSubscriber(string $subscriber): array;

    /**
     * @param list<string> $subscribers
     *
     * @return list<Subscription> every subscription of these subscribers in one read, oldest first
     */
    public function forSubscribers(array $subscribers): array;

    /**
     * @return list<Subscription> the trialing, active and past due subscriptions whose trial or period ended by `$at`:
     *                            the ones to renew, retry or expire, earliest end first; at most `$limit`
     */
    public function periodOverBy(DateTimeImmutable $at, ?int $limit = null): array;

    /**
     * @return list<Subscription> the pending subscriptions that await no order yet: the ones whose first period is to
     *                            be ordered, oldest first; at most `$limit`
     */
    public function pendingWithoutOrder(?int $limit = null): array;

    /**
     * @return list<Subscription> the pending subscriptions created before `$before`, whose first period was never
     *                            paid, oldest first; at most `$limit`
     */
    public function pendingCreatedBefore(DateTimeImmutable $before, ?int $limit = null): array;

    public function add(Subscription $subscription): void;
}
