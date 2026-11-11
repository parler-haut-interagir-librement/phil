<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application\Contract;

use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Phil\MonitoringBundle\Domain\ValueObject\HeartbeatStatus;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;

/**
 * The stable read model of the site's surveillance, for another bundle to show — a public status page, a dashboard:
 * the results of the checks, the state of each scheduled task, and the maintenance in force. Inject it by this
 * interface; its methods and the value objects they answer keep their shape within a major version.
 *
 * `health()` runs every check: a caller that shows it to the public keeps its answer for a while rather than running
 * the checks on every view.
 */
interface MonitoringStatus
{
    public function health(): HealthReport;

    /**
     * @return list<HeartbeatStatus>
     */
    public function heartbeats(): array;

    public function maintenance(): ?MaintenanceState;
}
