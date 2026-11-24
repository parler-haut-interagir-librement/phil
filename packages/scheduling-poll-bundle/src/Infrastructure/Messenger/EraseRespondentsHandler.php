<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Messenger;

use Phil\SchedulingPollBundle\Application\SchedulingPolls;

/**
 * Erases the names and addresses of the respondents of the polls closed longer ago than the delay of the
 * configuration, on `EraseRespondents`, the message the bundle tags it as handling. Handled twice, the second finds nothing more to erase.
 */
final readonly class EraseRespondentsHandler
{
    public function __construct(
        private SchedulingPolls $polls,
    ) {
    }

    public function __invoke(): void
    {
        $this->polls->eraseExpired();
    }
}
