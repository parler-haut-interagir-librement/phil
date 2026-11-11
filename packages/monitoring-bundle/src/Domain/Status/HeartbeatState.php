<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\Status;

/**
 * Where a scheduled task stands against its window: it ran in time, it is late, or it never ran since its heartbeats
 * are kept.
 */
enum HeartbeatState: string
{
    case OnTime = 'on_time';
    case Late = 'late';
    case Never = 'never';
}
