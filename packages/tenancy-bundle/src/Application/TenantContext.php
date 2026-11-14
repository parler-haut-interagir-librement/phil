<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application;

use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Domain\Exception\NoActiveTenant;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

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

    public function enter(TenantId $tenant): void
    {
        $this->current = $tenant;

        foreach ($this->activations as $activation) {
            $activation->activate($tenant);
        }
    }

    public function leave(): void
    {
        $this->current = null;

        foreach ($this->activations as $activation) {
            $activation->deactivate();
        }
    }

    /**
     * Runs work in a tenant, then returns to the tenant that was active before, or to none.
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
        $this->enter($tenant);

        try {
            return $work();
        } finally {
            $previous instanceof TenantId ? $this->enter($previous) : $this->leave();
        }
    }
}
