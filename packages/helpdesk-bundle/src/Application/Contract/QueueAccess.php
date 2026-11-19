<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Application\Contract;

use Phil\HelpdeskBundle\Application\Queue;

/**
 * Whether the person signed in works a queue: reads its requests, answers, assigns and changes their status.
 */
interface QueueAccess
{
    public function mayWork(Queue $queue): bool;
}
