<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Message;

/**
 * The schedule of this name is due: its job is kept and queued as a `RunReport`.
 */
final readonly class RunScheduledReport
{
    public function __construct(
        public string $schedule,
    ) {
    }
}
