<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Contract;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * What follows the active tenant of the unit of work — a request, a message, a command: the Doctrine filter, a
 * cache namespace, a log context. `TenantContext` calls every activation when a tenant is entered and left.
 */
interface TenantActivation
{
    public function activate(TenantId $tenant): void;

    /**
     * No tenant is active any more: what follows it fails closed.
     */
    public function deactivate(): void;
}
