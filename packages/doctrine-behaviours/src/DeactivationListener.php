<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use DateTimeImmutable;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Phil\DoctrineBehaviours\Contract\Deactivatable;
use Psr\Clock\ClockInterface;

/**
 * Keeps the date of a deactivation in step with the state of a `Deactivatable` entity: stamped as it is switched off,
 * unless it was given one, and forgotten as it is switched back on.
 */
final readonly class DeactivationListener
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof Deactivatable && $this->isOutOfStep($entity)) {
            $this->synchronize($entity);
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();

        foreach ($work->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Deactivatable && $this->isOutOfStep($entity)) {
                $this->synchronize($entity);
                $work->recomputeSingleEntityChangeSet($manager->getClassMetadata($entity::class), $entity);
            }
        }
    }

    /**
     * Whether the date says otherwise than the state: an inactive record with no date, or an active one with a date.
     */
    private function isOutOfStep(Deactivatable $entity): bool
    {
        return $entity->isActive() === $entity->deactivatedAt() instanceof DateTimeImmutable;
    }

    private function synchronize(Deactivatable $entity): void
    {
        $entity->stampDeactivated($entity->isActive() ? null : Utc::now($this->clock));
    }
}
