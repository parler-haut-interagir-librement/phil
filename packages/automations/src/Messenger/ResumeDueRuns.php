<?php

declare(strict_types=1);

namespace Phil\Automations\Messenger;

/**
 * Asks a worker to look for the runs whose time to go on has come and to send an `AdvanceRun` for each: the delays
 * longer than a transport keeps a message, the runs of a worker that stopped halfway.
 */
final readonly class ResumeDueRuns
{
    public function __construct(
        public int $limit = 500,
    ) {
    }
}
