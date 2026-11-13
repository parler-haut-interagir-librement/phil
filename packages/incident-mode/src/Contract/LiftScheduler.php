<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Contract;

use Phil\IncidentMode\Activation;

/**
 * Arranges for the automatic lifting of an activation at its end: a delayed message, a scheduled job.
 *
 * The end applies without it — an activation past its end is no longer in force —; the lifting records it in the
 * journal and sends its event.
 */
interface LiftScheduler
{
    public function schedule(Activation $activation): void;
}
