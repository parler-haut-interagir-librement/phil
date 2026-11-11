<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application\Check;

use Override;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\Status\HeartbeatState;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;

/**
 * The scheduled tasks as a check: degraded while one of them is late, naming them, since the site still answers.
 */
final readonly class HeartbeatsCheck implements HealthCheck
{
    public const string NAME = 'heartbeats';

    public function __construct(
        private Heartbeats $heartbeats,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        $statuses = $this->heartbeats->statuses();
        $late = [];

        foreach ($statuses as $status) {
            if (HeartbeatState::Late === $status->state) {
                $late[] = $status->task;
            }
        }

        if ([] !== $late) {
            return CheckOutcome::degraded(\sprintf('Late: %s.', implode(', ', $late)));
        }

        return CheckOutcome::up(\sprintf('%d tasks, none late.', \count($statuses)));
    }
}
