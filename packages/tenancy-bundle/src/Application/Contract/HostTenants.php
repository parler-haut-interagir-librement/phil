<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Contract;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The tenant a host serves, for an application that serves several tenants, such as several shops, each on its
 * own domain.
 */
interface HostTenants
{
    /**
     * @param string $host the host of the request, in lowercase and in its ASCII form, as HttpFoundation reads it
     *
     * @return ?TenantId the tenant the host serves, or `null` for a host of no tenant
     */
    public function tenantFor(string $host): ?TenantId;
}
