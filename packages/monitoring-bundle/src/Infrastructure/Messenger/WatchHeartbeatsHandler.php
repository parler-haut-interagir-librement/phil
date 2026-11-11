<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Messenger;

use Phil\MonitoringBundle\Application\DeadManSwitch;

/**
 * Runs a round of the dead-man switch when `WatchHeartbeats` is handled: the bundle tags it as the handler of that
 * message, which carries nothing to read.
 */
final readonly class WatchHeartbeatsHandler
{
    public function __construct(
        private DeadManSwitch $switch,
    ) {
    }

    public function __invoke(): void
    {
        $this->switch->watch();
    }
}
