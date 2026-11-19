<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\BlameResolver;

/**
 * Blames `Blameable` entities on whoever the resolver names: both names as one is persisted, unless it was given
 * them, and `updatedBy` as one is updated, unless the update sets it. When nobody is known, nothing is written, and
 * nothing either while a `BlameSuspension` it was given is suspended: during an erasure.
 */
final readonly class BlameListener
{
    public function __construct(
        private BlameResolver $resolver,
        private ?BlameSuspension $suspension = null,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        $who = $this->who();

        if ($entity instanceof Blameable && null !== $who) {
            $entity->blameCreated($who);
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $who = $this->who();

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

    private function who(): ?string
    {
        return true === $this->suspension?->isSuspended() ? null : $this->resolver->current();
    }
}
