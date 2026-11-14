<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * Sets the active tenant on the `phil_tenancy` filter of every entity manager that has it enabled. A manager
 * whose filter is disabled — cross-tenant work — is left alone.
 *
 * Leaving the tenant enables the filter afresh on every entity manager that knows it, a manager whose filter was
 * disabled for cross-tenant work included: Doctrine builds a new instance, which carries no tenant and matches no
 * tenant-scoped row, so that the next unit of work of a long-running worker never starts unfiltered. Without Doctrine
 * there is nothing to activate.
 */
final readonly class DoctrineTenantActivation implements TenantActivation
{
    public function __construct(
        private ?ManagerRegistry $doctrine = null,
    ) {
    }

    #[Override]
    public function activate(TenantId $tenant): void
    {
        foreach ($this->filteredManagers() as $manager) {
            $filter = $manager->getFilters()->getFilter(ActiveTenantFilter::NAME);
            \assert($filter instanceof ActiveTenantFilter);
            $filter->activate($tenant);
        }
    }

    #[Override]
    public function deactivate(): void
    {
        foreach ($this->managers() as $manager) {
            if (null === $manager->getConfiguration()->getFilterClassName(ActiveTenantFilter::NAME)) {
                continue;
            }

            if ($manager->getFilters()->isEnabled(ActiveTenantFilter::NAME)) {
                $manager->getFilters()->disable(ActiveTenantFilter::NAME);
            }

            $manager->getFilters()->enable(ActiveTenantFilter::NAME);
        }
    }

    /**
     * @return iterable<EntityManagerInterface>
     */
    private function filteredManagers(): iterable
    {
        foreach ($this->managers() as $manager) {
            if ($manager->getFilters()->isEnabled(ActiveTenantFilter::NAME)) {
                yield $manager;
            }
        }
    }

    /**
     * @return iterable<EntityManagerInterface>
     */
    private function managers(): iterable
    {
        foreach ($this->doctrine?->getManagers() ?? [] as $manager) {
            if ($manager instanceof EntityManagerInterface) {
                yield $manager;
            }
        }
    }
}
