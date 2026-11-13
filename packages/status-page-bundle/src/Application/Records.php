<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application;

use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;

/**
 * Incidents and maintenances as `RecordsReader` read them.
 */
final readonly class Records
{
    /**
     * @param list<Incident>             $incidents
     * @param list<ScheduledMaintenance> $maintenances
     */
    public function __construct(
        public array $incidents = [],
        public array $maintenances = [],
    ) {
    }
}
