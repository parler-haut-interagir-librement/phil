<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application;

use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Domain\Exception\NoActiveTenant;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Throwable;

/**
 * The tenant the current unit of work acts in: set once the request's tenant is resolved, a message's tenant
 * read from its stamp, or a command's tenant given on its command line. Entering and leaving a tenant carries
 * the change to every activation.
 *
 * `ActiveTenant` decides which tenant an account acts in; the context only holds the decision for the code that
 * runs after it.
 */
final class TenantContext
{
    private ?TenantId $current = null;

    /**
     * @param iterable<TenantActivation> $activations
     */
    public function __construct(
        private readonly iterable $activations = [],
    ) {
    }

    public function current(): ?TenantId
    {
        return $this->current;
    }

    /**
     * @throws NoActiveTenant when the unit of work acts in no tenant
     */
    public function require(): TenantId
    {
        return $this->current ?? throw NoActiveTenant::outsideATenant();
    }

    /**
     * Enters a tenant on every activation. An activation that fails leaves the unit of work in no tenant: every
     * activation is deactivated, the one that failed and those before it included, and the failure is thrown again.
     * Half an activation — the Doctrine filter on the new tenant, the cache still on the old one — never stands.
     */
    public function enter(TenantId $tenant): void
    {
        $this->current = $tenant;

        try {
            foreach ($this->activations as $activation) {
                $activation->activate($tenant);
            }
        } catch (Throwable $throwable) {
            $this->current = null;
            $this->deactivateAll();

            throw $throwable;
        }
    }

    /**
     * Leaves the tenant on every activation, even when one of them fails; the first failure is thrown once every
     * activation has been reached.
     */
    public function leave(): void
    {
        $this->current = null;
        $failure = $this->deactivateAll();

        if ($failure instanceof Throwable) {
            throw $failure;
        }
    }

    /**
     * Runs work in a tenant, then returns to the tenant that was active before, or to none: after the work, after a
     * failure of the work, and after a failure to enter the tenant.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function within(TenantId $tenant, callable $work): mixed
    {
        $previous = $this->current;

        try {
            $this->enter($tenant);

            return $work();
        } finally {
            $previous instanceof TenantId ? $this->enter($previous) : $this->leave();
        }
    }

    /**
     * Deactivates every activation; returns the first failure.
     */
    private function deactivateAll(): ?Throwable
    {
        $failure = null;

        foreach ($this->activations as $activation) {
            try {
                $activation->deactivate();
            } catch (Throwable $throwable) {
                $failure ??= $throwable;
            }
        }

        return $failure;
    }
}
