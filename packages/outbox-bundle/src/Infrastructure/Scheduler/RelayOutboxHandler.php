<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Scheduler;

use Phil\Outbox\RelayReport;
use Phil\OutboxBundle\Infrastructure\Worker\RelayRunner;

/**
 * Relays the outbox at each tick of the schedule: batch after batch until the outbox is empty or the batches of a tick
 * are spent, then gives the worker back. A tick that finds another run holding the lock does nothing.
 */
final readonly class RelayOutboxHandler
{
    public function __construct(
        private RelayRunner $runner,
        private int $batchSize,
        private int $maxBatches,
    ) {
    }

    public function __invoke(): ?RelayReport
    {
        return $this->runner->run($this->batchSize, $this->maxBatches, true);
    }
}
