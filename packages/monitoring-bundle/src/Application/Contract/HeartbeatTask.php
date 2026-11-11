<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application\Contract;

/**
 * A message of Symfony Scheduler that beats a task each time it is handled: the bundle records the beat once the
 * message's handler has returned, so a run that fails does not beat.
 */
interface HeartbeatTask
{
    /**
     * The name of the task, as declared under `phil_monitoring.heartbeats.tasks`.
     */
    public function heartbeatTask(): string;
}
