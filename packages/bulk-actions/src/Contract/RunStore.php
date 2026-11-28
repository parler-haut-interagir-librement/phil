<?php

declare(strict_types=1);

namespace Phil\BulkActions\Contract;

use Phil\BulkActions\Run\BulkRun;
use Phil\BulkActions\Run\RunId;

/**
 * Keeps the runs between their chunks. Kept in the database of the items, a save inside the chunk's transaction
 * commits the outcomes with the changes.
 */
interface RunStore
{
    public function save(BulkRun $run): void;

    public function get(RunId $id): ?BulkRun;
}
