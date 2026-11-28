<?php

declare(strict_types=1);

namespace Phil\CalendarImport\State;

/**
 * Where the state of each source is kept between two reads: a database table, a cache, a file. The library keeps
 * nothing itself, so that it serves every request of a worker alike.
 */
interface SourceStateStore
{
    public function load(string $sourceId): ?SourceState;

    public function save(SourceState $state): void;
}
