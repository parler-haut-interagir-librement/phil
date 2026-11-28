<?php

declare(strict_types=1);

namespace Phil\BulkActions\Memory;

use Phil\BulkActions\Contract\RunStore;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunId;

/**
 * A run store in an array, for tests and for a single process. It keeps what toArray() gives and rebuilds the run on
 * each read, as a real store would.
 */
final class InMemoryRunStore implements RunStore
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function save(BulkRun $run): void
    {
        $this->rows[$run->id->value] = $run->toArray();
    }

    public function get(RunId $id): ?BulkRun
    {
        $row = $this->rows[$id->value] ?? null;

        return null === $row ? null : BulkRun::fromArray($row);
    }
}
