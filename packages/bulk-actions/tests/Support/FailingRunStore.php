<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use Phil\BulkActions\Contract\RunStore;
use Phil\BulkActions\Memory\InMemoryRunStore;
use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunId;
use RuntimeException;

/**
 * A run store that fails the saves it is told to, as a lost database connection would.
 */
final class FailingRunStore implements RunStore
{
    public int $failures = 0;

    private readonly InMemoryRunStore $store;

    public function __construct()
    {
        $this->store = new InMemoryRunStore();
    }

    public function save(BulkRun $run): void
    {
        if ($this->failures > 0) {
            --$this->failures;

            throw new RuntimeException('The database went away.');
        }

        $this->store->save($run);
    }

    public function get(RunId $id): ?BulkRun
    {
        return $this->store->get($id);
    }
}
