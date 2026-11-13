<?php

declare(strict_types=1);

namespace Phil\Stockroom\Memory;

use Override;
use Phil\Stockroom\Contract\Items;
use Phil\Stockroom\Item;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The items in memory, for tests and short scripts; emptied by `reset()` between the requests of a worker.
 */
final class InMemoryItems implements Items, ResetInterface
{
    /** @var array<string, Item> */
    private array $items = [];

    /** @var array<string, string> the identifiers of the items, by code */
    private array $codes = [];

    #[Override]
    public function get(string $id): ?Item
    {
        return $this->items[$id] ?? null;
    }

    #[Override]
    public function byCode(string $code): ?Item
    {
        return $this->items[$this->codes[$code] ?? ''] ?? null;
    }

    #[Override]
    public function save(Item $item): void
    {
        $former = $this->items[$item->id] ?? null;

        if ($former instanceof Item) {
            unset($this->codes[$former->code]);
        }

        $this->items[$item->id] = $item;
        $this->codes[$item->code] = $item->id;
    }

    #[Override]
    public function reset(): void
    {
        $this->items = [];
        $this->codes = [];
    }
}
