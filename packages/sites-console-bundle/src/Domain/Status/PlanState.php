<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Status;

/**
 * Where an update plan stands: planned, running, completed when every site succeeded, stopped at the first failure.
 */
enum PlanState: string
{
    case Planned = 'planned';
    case Running = 'running';
    case Completed = 'completed';
    case Stopped = 'stopped';

    public function isFinished(): bool
    {
        return self::Completed === $this || self::Stopped === $this;
    }
}
