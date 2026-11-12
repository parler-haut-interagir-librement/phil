<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Phil\DoctrineBehaviours\Contract\Versionable;

/**
 * Forks a frozen `Versionable` entity as one of its versioned fields changes: the change goes to the next number, and
 * the snapshot of the frozen one stays. A change to anything else — a timestamp, a publication state — forks nothing.
 */
final readonly class VersionListener
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();

        foreach ($work->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Versionable && $entity->isVersionFrozen() && [] !== array_intersect_key($work->getEntityChangeSet($entity), $entity->versionedState())) {
                $entity->forkVersion();
                $work->recomputeSingleEntityChangeSet($manager->getClassMetadata($entity::class), $entity);
            }
        }
    }
}
