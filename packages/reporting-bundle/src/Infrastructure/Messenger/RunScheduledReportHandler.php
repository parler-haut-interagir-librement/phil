<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Messenger;

use Phil\ReportingBundle\Application\Message\RunScheduledReport;
use Phil\ReportingBundle\Application\Report\Schedules;

/**
 * Queues the job of a due schedule, over its period at this moment.
 */
final readonly class RunScheduledReportHandler
{
    public function __construct(
        private Schedules $schedules,
        private ReportQueue $queue,
    ) {
    }

    public function __invoke(RunScheduledReport $message): void
    {
        $this->queue->queue($this->schedules->job($message->schedule));
    }
}
