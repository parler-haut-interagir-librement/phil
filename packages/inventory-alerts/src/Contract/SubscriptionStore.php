<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Contract;

use DateTimeImmutable;
use Phil\EmailAddress\EmailAddress;
use Phil\InventoryAlerts\Subscription\Price;
use Phil\InventoryAlerts\Subscription\Subscription;
use Phil\InventoryAlerts\Subscription\SubscriptionKind;

/**
 * Where the subscriptions are kept: a table of the application. It stores what a subscription holds and nothing
 * more, and serves no other use of the addresses.
 */
interface SubscriptionStore
{
    /**
     * Inserts the subscription, or replaces the one with its identifier.
     */
    public function save(Subscription $subscription): void;

    public function find(string $id): ?Subscription;

    /**
     * The active subscription of an address to a SKU for a kind of alert, if any.
     */
    public function findActive(SubscriptionKind $kind, string $sku, EmailAddress $email): ?Subscription;

    /**
     * The first active subscriptions to a SKU for a kind of alert, in the order they were made — subscription date,
     * then identifier —, at most $limit (1 or more). For price-drop alerts, only those whose threshold the price is
     * at or below.
     *
     * @return list<Subscription>
     */
    public function active(SubscriptionKind $kind, string $sku, int $limit, ?Price $price = null): array;

    /**
     * Marks the subscriptions as notified at a time: they are no longer active.
     *
     * @param non-empty-list<string> $ids
     */
    public function markNotified(array $ids, DateTimeImmutable $at): void;

    public function delete(string $id): void;

    /**
     * Deletes the subscriptions notified before a time, and returns how many.
     */
    public function deleteNotifiedBefore(DateTimeImmutable $before): int;
}
