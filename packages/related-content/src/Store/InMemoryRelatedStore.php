<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Store;

use Phil\RelatedContent\Contract\RelatedStore;
use Phil\RelatedContent\RelatedSet;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The kept neighbours in memory, with the reverse index `pointingAt()` reads: for tests, and for a process that holds
 * them for its own life. An application keeps them in its database or its cache through its own `RelatedStore`.
 */
final class InMemoryRelatedStore implements RelatedStore, ResetInterface
{
    /**
     * @var array<string, RelatedSet>
     */
    private array $sets = [];

    /**
     * @var array<string, array<string, true>> by neighbour, the items that show it
     */
    private array $pointing = [];

    public function save(RelatedSet $set): void
    {
        $this->remove($set->itemId);
        $this->sets[$set->itemId] = $set;

        foreach ($set->ids() as $id) {
            $this->pointing[$id][$set->itemId] = true;
        }
    }

    public function find(string $id): ?RelatedSet
    {
        return $this->sets[$id] ?? null;
    }

    public function remove(string $id): void
    {
        foreach (($this->sets[$id] ?? null)?->ids() ?? [] as $neighbour) {
            unset($this->pointing[$neighbour][$id]);

            if ([] === $this->pointing[$neighbour]) {
                unset($this->pointing[$neighbour]);
            }
        }

        unset($this->sets[$id]);
    }

    public function pointingAt(string $id, int $limit): array
    {
        return array_map(strval(...), \array_slice(array_keys($this->pointing[$id] ?? []), 0, max(0, $limit)));
    }

    public function reset(): void
    {
        $this->sets = [];
        $this->pointing = [];
    }
}
