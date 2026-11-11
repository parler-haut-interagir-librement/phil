<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\Contract;

use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;

/**
 * One check of the site's health: the database answers, a cache pool keeps a value, a queue is not piling up. A service
 * implementing it is tagged `phil_monitoring.health_check` by autoconfiguration and runs in every round.
 *
 * A check answers `down` only when the site cannot work without what it checks, since a monitor then takes the site
 * out of service; anything to look at but not fatal is `degraded`. A check that throws counts as down, its exception
 * logged and never shown. A check is fast — it runs on every readiness probe — and keeps nothing between two rounds.
 */
interface HealthCheck
{
    /**
     * The name of the check, shown to monitors: an identifier such as `database` or `cache.app`.
     */
    public function name(): string;

    public function check(): CheckOutcome;
}
