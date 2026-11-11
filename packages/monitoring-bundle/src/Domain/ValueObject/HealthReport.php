<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;

/**
 * The checks of one round, in the order they ran, and the moment the round started. The site is as healthy as its
 * worst check, and up when it has none.
 */
final readonly class HealthReport
{
    /**
     * @param list<CheckResult> $results
     */
    public function __construct(
        public array $results,
        public DateTimeImmutable $checkedAt,
    ) {
    }

    public function status(): HealthStatus
    {
        $status = HealthStatus::Up;

        foreach ($this->results as $result) {
            $status = $status->worst($result->status());
        }

        return $status;
    }
}
