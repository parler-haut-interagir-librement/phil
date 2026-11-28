<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use Phil\BulkActions\Contract\RunDispatcher;
use Phil\BulkActions\Messenger\ExecuteBulkRun;
use Phil\BulkActions\Run\RunId;

/**
 * A queue of messages, as a transport keeps them, that a worker loop consumes.
 */
final class QueuedDispatcher implements RunDispatcher
{
    /** @var list<ExecuteBulkRun> */
    public array $queue = [];

    /** @var list<string> every message sent, serialized as a transport would */
    public array $sent = [];

    public function dispatch(RunId $run): void
    {
        $message = new ExecuteBulkRun($run->value);
        $this->sent[] = serialize($message);
        $this->queue[] = $message;
    }
}
