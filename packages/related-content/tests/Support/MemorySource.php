<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Support;

use Phil\RelatedContent\Contract\ItemSource;
use Phil\RelatedContent\Item;

/**
 * The published items in memory: every other item is a candidate, in the order given; it remembers what it was asked.
 */
final class MemorySource implements ItemSource
{
    /**
     * @var array<string, Item>
     */
    private array $items = [];

    /**
     * @var list<string>
     */
    public array $asked = [];

    public function __construct(Item ...$items)
    {
        foreach ($items as $item) {
            $this->put($item);
        }
    }

    public function put(Item $item): void
    {
        $this->items[$item->id] = $item;
    }

    public function retire(string $id): void
    {
        unset($this->items[$id]);
    }

    public function item(string $id): ?Item
    {
        $this->asked[] = $id;

        return $this->items[$id] ?? null;
    }

    public function candidates(Item $item): iterable
    {
        foreach ($this->items as $candidate) {
            yield $candidate;
        }
    }
}
