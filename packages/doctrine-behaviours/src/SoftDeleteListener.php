<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;
use Psr\Clock\ClockInterface;

/**
 * Turns the removal of a `SoftDeletable` entity into its deletion date: the entity stays managed and its row stays,
 * with `deleted_at` set. An entity already deleted is removed for good.
 */
final readonly class SoftDeleteListener
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();

        foreach ($work->getScheduledEntityDeletions() as $entity) {
            if (!$entity instanceof SoftDeletable || $entity->isDeleted()) {
                continue;
            }

            $now = Utc::now($this->clock);
            $entity->markDeleted($now);
            $manager->persist($entity);
            $work->scheduleExtraUpdate($entity, ['deletedAt' => [null, $now]]);
            $work->setOriginalEntityProperty(spl_object_id($entity), 'deletedAt', $now);
        }
    }
}
