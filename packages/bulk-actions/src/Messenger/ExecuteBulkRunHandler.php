<?php

declare(strict_types=1);

namespace Phil\BulkActions\Messenger;

use DateTimeImmutable;
use Phil\BulkActions\BulkExecutor;
use Phil\BulkActions\Contract\RunDispatcher;
use Phil\BulkActions\Run\RunId;

/**
 * Handles an ExecuteBulkRun: executes one chunk, then asks for the next one while the run has more to do. A message
 * delivered twice finds the chunk's items handled already, or the run over, and changes nothing more.
 */
final readonly class ExecuteBulkRunHandler
{
    public function __construct(
        private BulkExecutor $executor,
        private RunDispatcher $dispatcher,
    ) {
    }

    public function __invoke(ExecuteBulkRun $message): void
    {
        $run = RunId::fromString($message->run);

        if ($this->executor->execute($run, new DateTimeImmutable())) {
            $this->dispatcher->dispatch($run);
        }
    }
}
