<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\BlameResolver;

/**
 * Blames `Blameable` entities on whoever the resolver names: both names as one is persisted, unless it was given
 * them, and `updatedBy` as one is updated, unless the update sets it. When nobody is known, nothing is written.
 */
final readonly class BlameListener
{
    public function __construct(
        private BlameResolver $resolver,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        $who = $this->resolver->current();

        if ($entity instanceof Blameable && null !== $who) {
            $entity->blameCreated($who);
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $who = $this->resolver->current();

        if (null === $who) {
            return;
        }

        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();

        foreach ($work->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Blameable && !\array_key_exists('updatedBy', $work->getEntityChangeSet($entity))) {
                $entity->blameUpdated($who);
                $work->recomputeSingleEntityChangeSet($manager->getClassMetadata($entity::class), $entity);
            }
        }
    }
}
