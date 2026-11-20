<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Phil\TenancyBundle\Infrastructure\Exception\CrossTenantWrite;

/**
 * Refuses a flush that writes a tenant-scoped entity of another tenant than the active one. The filter restricts
 * reads; in a shared database without row-level security, nothing else stops an entity built or loaded for one tenant
 * from being inserted, updated or deleted while another is active.
 *
 * The guard applies while the `phil_tenancy` filter of the entity manager is enabled and carries a tenant. A unit of
 * work in no tenant — a migration, a fixture, a command run without `--tenant` — and work across tenants, which
 * disables or suspends the filter, write as before.
 */
final class TenantWriteGuard
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $filters = $manager->getFilters();

        if (!$filters->isEnabled(ActiveTenantFilter::NAME)) {
            return;
        }

        $filter = $filters->getFilter(ActiveTenantFilter::NAME);
        \assert($filter instanceof ActiveTenantFilter);
        $active = $filter->tenant();

        if (null === $active) {
            return;
        }

        $unitOfWork = $manager->getUnitOfWork();

        foreach ([$unitOfWork->getScheduledEntityInsertions(), $unitOfWork->getScheduledEntityUpdates(), $unitOfWork->getScheduledEntityDeletions()] as $entities) {
            foreach ($entities as $entity) {
                $metadata = $manager->getClassMetadata($entity::class);
                // The scope is read on the root of an inheritance hierarchy, as the filter reads it.
                $field = TenantColumn::field($manager->getClassMetadata($metadata->rootEntityName));

                if (null === $field) {
                    continue;
                }

                $tenant = EntityTenant::of($manager, $metadata, $field, $entity);

                if ($tenant !== $active) {
                    throw CrossTenantWrite::of($metadata->getName(), $tenant, $active);
                }
            }
        }
    }
}
