<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Contract;

use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\ValueObject\Component;

/**
 * Where the state of each component comes from before incidents and maintenances are applied: the application's
 * declaration, the checks of a monitoring tool, or a source of the application's own. A component left out is
 * operational.
 */
interface ComponentStatuses
{
    /**
     * @param list<Component> $components
     *
     * @return array<string, ComponentStatus> the state of components by id
     */
    public function statuses(array $components): array;
}
