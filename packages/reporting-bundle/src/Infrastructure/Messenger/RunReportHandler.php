<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Messenger;

use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Message\RunReport;
use Phil\ReportingBundle\Application\Report\ReportRunner;
use Phil\ReportingBundle\Domain\Exception\ReportingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Runs the job of a `RunReport`. A failure of the bundle — a job gone, an unknown report, format or delivery, too
 * many rows — is never retried, and the job is forgotten with its recipients; anything else, a mail server out of
 * reach, is left for Messenger to retry with the job still kept.
 */
final readonly class RunReportHandler
{
    public function __construct(
        private ReportRunner $runner,
        private JobStore $jobs,
    ) {
    }

    public function __invoke(RunReport $message): void
    {
        try {
            $this->runner->run($message->job);
        } catch (ReportingException $reportingException) {
            $this->jobs->remove($message->job);

            throw new UnrecoverableMessageHandlingException($reportingException->getMessage(), $reportingException->getCode(), previous: $reportingException);
        }
    }
}
