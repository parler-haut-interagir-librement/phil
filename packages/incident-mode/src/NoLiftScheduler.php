<?php

declare(strict_types=1);

namespace Phil\IncidentMode;

use Override;
use Phil\IncidentMode\Contract\LiftScheduler;

/**
 * Schedules nothing: the expired activations are lifted by `IncidentMode::liftExpired()`, called by a scheduled task.
 */
final readonly class NoLiftScheduler implements LiftScheduler
{
    #[Override]
    public function schedule(Activation $activation): void
    {
    }
}
