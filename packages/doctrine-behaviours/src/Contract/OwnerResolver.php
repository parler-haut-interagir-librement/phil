<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use Phil\DoctrineBehaviours\OwnerId;

/**
 * Who new records belong to now, or null when nobody is known — a command, a scheduled job.
 */
interface OwnerResolver
{
    public function current(): ?OwnerId;
}
