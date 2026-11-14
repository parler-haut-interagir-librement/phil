<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application;

use Phil\TenancyBundle\Application\Contract\ActiveTenantStore;
use Phil\TenancyBundle\Application\Contract\ReachableTenantsProvider;
use Phil\TenancyBundle\Domain\Exception\TenantNotReachable;
use Phil\TenancyBundle\Domain\Resolution\ActiveTenantRule;
use Phil\TenancyBundle\Domain\Resolution\Resolution;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The active tenant of the current account: the rules of {@see ActiveTenantRule} applied to the two ports.
 *
 * Registered by the bundle, as a public service, once `phil_tenancy.reachable_tenants` names a provider.
 */
final readonly class ActiveTenant
{
    public function __construct(
        private ActiveTenantStore $store,
        private ReachableTenantsProvider $provider,
    ) {
    }

    /**
     * Resolves the active tenant, and forgets a remembered choice that is no longer reachable.
     */
    public function resolve(): Resolution
    {
        $resolution = ActiveTenantRule::resolve($this->store->remembered(), $this->provider->reachable());

        if ($resolution->staleChoiceDropped) {
            $this->store->forget();
        }

        return $resolution;
    }

    /**
     * Whether the account can act in a tenant: whether its reachable tenants hold it.
     */
    public function canReach(TenantId $tenant): bool
    {
        return $this->provider->reachable()->contains($tenant);
    }

    /**
     * Switches to a tenant the account can reach, and remembers it.
     *
     * @throws TenantNotReachable when the account cannot act in the requested tenant; the remembered choice is
     *                            left as it was
     */
    public function switchTo(TenantId $tenant): void
    {
        $this->store->remember(ActiveTenantRule::switchTo($tenant, $this->provider->reachable()));
    }

    public function leave(): void
    {
        $this->store->forget();
    }
}
