<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Messenger;

use Phil\SchedulingPollBundle\Application\SchedulingPolls;

/**
 * Closes the polls past their deadline on their best date, on `CloseDuePolls`, the message the bundle tags it as
 * handling. Two workers that handle it at once close each poll once:
 * the closing is a conditional write.
 */
final readonly class CloseDuePollsHandler
{
    /**
     * @param positive-int $batch the polls closed at most per message
     */
    public function __construct(
        private SchedulingPolls $polls,
        private int $batch = 100,
    ) {
    }

    public function __invoke(): void
    {
        $this->polls->closeDue($this->batch);
    }
}
