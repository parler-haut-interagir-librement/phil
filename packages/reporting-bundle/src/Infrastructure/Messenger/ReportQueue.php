<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Messenger;

use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Message\RunReport;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A report sent to a worker: the job kept in the job store, and a `RunReport` dispatched with its identifier only.
 */
final readonly class ReportQueue
{
    public function __construct(
        private JobStore $jobs,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Gives the job's identifier, which `ReportExported` names once the report is exported.
     */
    public function queue(ReportJob $job): string
    {
        $id = $this->jobs->keep($job);
        $this->bus->dispatch(new RunReport($id));

        return $id;
    }
}
