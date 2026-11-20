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
 * **The identity map follows the tenant.** Doctrine answers `find()`, a reference and a lazy association from its
 * identity map before any query, so the filter never sees them. When the tenant of a filtered manager changes —
 * another tenant entered, or the tenant left — the tenant-scoped entities it manages are detached: an entity loaded
 * for one tenant is never returned to another. An entity scheduled for insertion or deletion stays, so that its
 * write is not lost silently; `TenantWriteGuard` refuses to flush it under another tenant. Flush before switching
 * tenants, and load an entity again after `TenantContext::within()`.
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
        foreach ($this->filteredManagers() as $manager => $filter) {
            if ($filter->tenant() !== $tenant->value) {
                $this->detachTenantScoped($manager);
            }

            $filter->activate($tenant);
        }
    }

    #[Override]
    public function deactivate(): void
    {
        foreach ($this->filteredManagers() as $manager => $filter) {
            if (null !== $filter->tenant()) {
                $this->detachTenantScoped($manager);
            }
        }

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
     * Detaches every tenant-scoped entity of the identity map, except those scheduled for insertion or deletion.
     */
    private function detachTenantScoped(EntityManagerInterface $manager): void
    {
        $unitOfWork = $manager->getUnitOfWork();

        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            if (null === TenantColumn::field($manager->getClassMetadata($class))) {
                continue;
            }

            foreach ($entities as $entity) {
                if ($unitOfWork->isScheduledForInsert($entity) || $unitOfWork->isScheduledForDelete($entity)) {
                    continue;
                }

                $manager->detach($entity);
            }
        }
    }

    /**
     * @return iterable<EntityManagerInterface, ActiveTenantFilter>
     */
    private function filteredManagers(): iterable
    {
        foreach ($this->managers() as $manager) {
            if ($manager->getFilters()->isEnabled(ActiveTenantFilter::NAME)) {
                $filter = $manager->getFilters()->getFilter(ActiveTenantFilter::NAME);
                \assert($filter instanceof ActiveTenantFilter);

                yield $manager => $filter;
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
