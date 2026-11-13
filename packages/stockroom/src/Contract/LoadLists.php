<?php

declare(strict_types=1);

namespace Phil\Stockroom\Contract;

use Phil\Stockroom\LoadList;

/**
 * Where the load lists of the events are kept, one per event.
 */
interface LoadLists
{
    public function get(string $event): ?LoadList;

    /**
     * Adds the list, or replaces the one of its event.
     */
    public function save(LoadList $list): void;
}
