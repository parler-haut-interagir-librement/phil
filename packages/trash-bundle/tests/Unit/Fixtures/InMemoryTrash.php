<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Unit\Fixtures;

use DateTimeImmutable;
use Phil\TrashBundle\Domain\Model\TrashItem;
use Phil\TrashBundle\Domain\Repository\TrashItemRepository;

final class InMemoryTrash implements TrashItemRepository
{
    /** @var array<string, TrashItem> */
    public array $items = [];

    public function save(TrashItem $item): void
    {
        $this->items[$item->id] = $item;
    }

    public function remove(TrashItem $item): void
    {
        unset($this->items[$item->id]);
    }

    public function get(string $id): ?TrashItem
    {
        return $this->items[$id] ?? null;
    }

    public function list(?string $resourceKey = null, int $limit = 50, int $offset = 0): array
    {
        return \array_slice(array_values(array_filter($this->items, static fn (TrashItem $item): bool => null === $resourceKey || $item->resource()->key === $resourceKey)), $offset, $limit);
    }

    public function expired(DateTimeImmutable $now, int $limit): array
    {
        $expired = array_values(array_filter($this->items, static fn (TrashItem $item): bool => $item->isExpiredAt($now)));
        usort($expired, static fn (TrashItem $a, TrashItem $b): int => $a->purgeAfter() <=> $b->purgeAfter());

        return \array_slice($expired, 0, $limit);
    }
}
