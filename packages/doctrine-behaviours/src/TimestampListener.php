<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Phil\DoctrineBehaviours\Contract\Timestampable;
use Psr\Clock\ClockInterface;

/**
 * Stamps `Timestampable` entities: both dates as one is persisted, unless it was given them, and `updatedAt` as one
 * is updated, unless the update sets it.
 */
final readonly class TimestampListener
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof Timestampable) {
            $entity->stampCreated(Utc::now($this->clock));
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();

        foreach ($work->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Timestampable && !\array_key_exists('updatedAt', $work->getEntityChangeSet($entity))) {
                $entity->stampUpdated(Utc::now($this->clock));
                $work->recomputeSingleEntityChangeSet($manager->getClassMetadata($entity::class), $entity);
            }
        }
    }
}
