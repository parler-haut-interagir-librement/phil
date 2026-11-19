<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Fixture;

use Override;
use Phil\HelpdeskBundle\Application\Contract\QueueAccess;
use Phil\HelpdeskBundle\Application\Queue;

/**
 * The person signed in works the queues named, changed as the test goes.
 */
final class FixedAccess implements QueueAccess
{
    /**
     * @param list<string> $queues
     */
    public function __construct(
        public array $queues = [],
    ) {
    }

    #[Override]
    public function mayWork(Queue $queue): bool
    {
        return \in_array($queue->name->value, $this->queues, true);
    }
}
