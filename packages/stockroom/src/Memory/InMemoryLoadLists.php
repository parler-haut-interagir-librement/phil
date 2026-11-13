<?php

declare(strict_types=1);

namespace Phil\Stockroom\Memory;

use Override;
use Phil\Stockroom\Contract\LoadLists;
use Phil\Stockroom\LoadList;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The load lists in memory, for tests and short scripts; emptied by `reset()` between the requests of a worker.
 */
final class InMemoryLoadLists implements LoadLists, ResetInterface
{
    /** @var array<string, LoadList> */
    private array $lists = [];

    #[Override]
    public function get(string $event): ?LoadList
    {
        return $this->lists[$event] ?? null;
    }

    #[Override]
    public function save(LoadList $list): void
    {
        $this->lists[$list->event] = $list;
    }

    #[Override]
    public function reset(): void
    {
        $this->lists = [];
    }
}
