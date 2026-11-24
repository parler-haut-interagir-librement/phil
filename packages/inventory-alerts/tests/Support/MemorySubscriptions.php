<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Support;

use DateTimeImmutable;
use Phil\EmailAddress\EmailAddress;
use Phil\InventoryAlerts\Contract\SubscriptionStore;
use Phil\InventoryAlerts\Subscription\Price;
use Phil\InventoryAlerts\Subscription\Subscription;
use Phil\InventoryAlerts\Subscription\SubscriptionKind;

/**
 * The subscriptions of a test, kept in memory as a table would keep them.
 */
final class MemorySubscriptions implements SubscriptionStore
{
    /** @var array<string, Subscription> */
    public array $rows = [];

    /** @var list<int> */
    public array $limits = [];

    public function save(Subscription $subscription): void
    {
        $this->rows[$subscription->id] = $subscription;
    }

    public function find(string $id): ?Subscription
    {
        return $this->rows[$id] ?? null;
    }

    public function findActive(SubscriptionKind $kind, string $sku, EmailAddress $email): ?Subscription
    {
        foreach ($this->rows as $row) {
            if ($row->isActive() && $row->kind === $kind && $row->sku === $sku && $row->email->equals($email)) {
                return $row;
            }
        }

        return null;
    }

    public function active(SubscriptionKind $kind, string $sku, int $limit, ?Price $price = null): array
    {
        $this->limits[] = $limit;
        $rows = array_values(array_filter(
            $this->rows,
            static fn (Subscription $row): bool => $row->isActive() && $row->kind === $kind && $row->sku === $sku
                && (!$price instanceof Price || ($row->threshold instanceof Price && $price->isAtOrBelow($row->threshold))),
        ));
        usort($rows, static fn (Subscription $a, Subscription $b): int => [$a->subscribedAt, $a->id] <=> [$b->subscribedAt, $b->id]);

        return \array_slice($rows, 0, $limit);
    }

    public function markNotified(array $ids, DateTimeImmutable $at): void
    {
        foreach ($ids as $id) {
            $this->rows[$id] = $this->rows[$id]->notified($at);
        }
    }

    public function delete(string $id): void
    {
        unset($this->rows[$id]);
    }

    public function deleteNotifiedBefore(DateTimeImmutable $before): int
    {
        $deleted = 0;

        foreach ($this->rows as $id => $row) {
            if (null !== $row->notifiedAt && $row->notifiedAt < $before) {
                unset($this->rows[$id]);
                ++$deleted;
            }
        }

        return $deleted;
    }
}
