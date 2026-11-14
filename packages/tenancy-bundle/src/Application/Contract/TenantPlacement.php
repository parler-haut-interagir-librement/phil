<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Contract;

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * Where each tenant's data lives: in the shared database, or in a database of its own. The bundle only declares the
 * question; a database bundle, or the application, answers it and moves the connection.
 */
interface TenantPlacement
{
    /**
     * Asked each time a unit of work enters the tenant — a request, a message, a command —, so keep it cheap: from
     * memory, a cache, or one indexed query on a connection that does not follow the tenant.
     *
     * A tenant the application does not know is placed in the shared database, where the Doctrine filter shows it
     * nothing.
     */
    public function placementOf(TenantId $tenant): Placement;
}
