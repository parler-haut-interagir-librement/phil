<?php

declare(strict_types=1);

namespace Phil\BulkActions\Contract;

use Phil\BulkActions\Run\RunId;

/**
 * Asks for the next chunk of a run to be executed, usually by a message on a bus that carries the run's identifier
 * and nothing else.
 */
interface RunDispatcher
{
    public function dispatch(RunId $run): void;
}
