<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Event;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * A request's tenant was found from its host and entered, before the firewall: what a shop listens to in order to
 * open its own catalogue and cart.
 */
final readonly class HostTenantResolved
{
    public function __construct(
        public string $host,
        public TenantId $tenant,
    ) {
    }
}
