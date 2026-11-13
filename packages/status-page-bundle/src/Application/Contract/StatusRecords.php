<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Contract;

use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;

/**
 * Where incidents and planned maintenances are kept: the configuration, a JSON file, or a store of the application's
 * own. The page sorts and filters them; the source only gives them.
 */
interface StatusRecords
{
    /**
     * @return list<Incident>
     */
    public function incidents(): array;

    /**
     * @return list<ScheduledMaintenance>
     */
    public function maintenances(): array;
}
