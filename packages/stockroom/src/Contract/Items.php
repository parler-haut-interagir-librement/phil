<?php

declare(strict_types=1);

namespace Phil\Stockroom\Contract;

use Phil\Stockroom\Item;

/**
 * Where the items are kept.
 */
interface Items
{
    public function get(string $id): ?Item;

    /**
     * The item whose label carries the code.
     */
    public function byCode(string $code): ?Item;

    /**
     * Adds the item, or replaces the one of its identifier.
     */
    public function save(Item $item): void;
}
