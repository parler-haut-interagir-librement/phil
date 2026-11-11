<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application\Message;

/**
 * Asks for a round of the dead-man switch: the late tasks are alerted. It carries nothing — schedule it with Symfony
 * Scheduler, every minute for instance, on one schedule only.
 */
final readonly class WatchHeartbeats
{
}
