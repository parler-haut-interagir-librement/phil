<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Double;

use Override;
use Phil\CalendarImport\State\SourceState;
use Phil\CalendarImport\State\SourceStateStore;

/**
 * The states of the sources in memory, for the tests only: a worker would keep them from one request to the next.
 */
final class InMemorySourceStateStore implements SourceStateStore
{
    /** @var array<string, SourceState> */
    public array $states = [];

    public int $saves = 0;

    #[Override]
    public function load(string $sourceId): ?SourceState
    {
        return $this->states[$sourceId] ?? null;
    }

    #[Override]
    public function save(SourceState $state): void
    {
        $this->states[$state->sourceId] = $state;
        ++$this->saves;
    }
}
