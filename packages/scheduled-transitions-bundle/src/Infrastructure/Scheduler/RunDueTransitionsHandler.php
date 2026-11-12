<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler;

use Phil\ScheduledTransitionsBundle\Application\RunReport;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;

final readonly class RunDueTransitionsHandler
{
    public function __construct(
        private Scheduler $scheduler,
    ) {
    }

    public function __invoke(RunDueTransitions $message): RunReport
    {
        return $this->scheduler->runDue($message->limit);
    }
}
