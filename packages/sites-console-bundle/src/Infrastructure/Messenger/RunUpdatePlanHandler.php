<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Messenger;

use Phil\SitesConsoleBundle\Application\Console\UpdatePlans;
use Phil\SitesConsoleBundle\Application\Message\RunUpdatePlan;

/**
 * Runs an update plan in a worker.
 */
final readonly class RunUpdatePlanHandler
{
    public function __construct(
        private UpdatePlans $plans,
    ) {
    }

    public function __invoke(RunUpdatePlan $message): void
    {
        $this->plans->run($message->planId, $message->batches, $message->actor);
    }
}
