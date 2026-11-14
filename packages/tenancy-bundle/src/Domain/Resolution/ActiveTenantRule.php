<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Resolution;

use Phil\TenancyBundle\Domain\Exception\TenantNotReachable;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The two rules of the active tenant, as pure functions of the reachable set.
 *
 * Both are checked against the entitlements the application already holds. Neither asks the identity provider
 * anything, which is why a switch works instantly and keeps working while the provider is down.
 */
final readonly class ActiveTenantRule
{
    /**
     * Resolves the active tenant from the remembered choice, if any.
     *
     * 1. A remembered choice that is still reachable is the active tenant.
     * 2. A remembered choice that is no longer reachable is dropped, never replaced by a neighbour.
     * 3. Without a usable choice, a single reachable tenant is active, since there is nothing to choose.
     * 4. Without a usable choice and with several reachable tenants, a selection is required: the bundle never
     *    picks one, because the first tenant of a list is an accident of ordering, not a decision.
     * 5. With no reachable tenant, none is active.
     */
    public static function resolve(?TenantId $remembered, ReachableTenants $reachable): Resolution
    {
        $dropped = false;

        if ($remembered instanceof TenantId) {
            if ($reachable->contains($remembered)) {
                return Resolution::active($remembered, $reachable);
            }

            $dropped = true;
        }

        if ($reachable->isEmpty()) {
            return Resolution::noneReachable($dropped);
        }

        $single = $reachable->single();

        if ($single instanceof TenantId) {
            return Resolution::active($single, $reachable, $dropped);
        }

        return Resolution::selectionRequired($reachable, $dropped);
    }

    /**
     * Checks a requested switch.
     *
     * @throws TenantNotReachable when the account cannot act in the requested tenant
     */
    public static function switchTo(TenantId $requested, ReachableTenants $reachable): TenantId
    {
        if (!$reachable->contains($requested)) {
            throw TenantNotReachable::forTenant($requested);
        }

        return $requested;
    }
}
