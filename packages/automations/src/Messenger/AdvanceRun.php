<?php

declare(strict_types=1);

namespace Phil\Automations\Messenger;

/**
 * Asks a worker to take a run on from where it stands. It carries the run's id and nothing else: the run, its
 * payload and its steps are read from the store when the message is handled.
 */
final readonly class AdvanceRun
{
    public function __construct(
        public string $runId,
    ) {
    }
}
