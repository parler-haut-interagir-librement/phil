<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;
use Psr\Clock\ClockInterface;

/**
 * Turns the removal of a `SoftDeletable` entity into its deletion date: the entity stays managed and its whole row
 * stays, every column as it was, with `deleted_at` set. An entity already deleted is removed for good, and
 * `SoftDeletes::purge()` deletes one in a single flush.
 *
 * Register it after the `onFlush` listeners that must see the removal as a deletion — a search index that drops the
 * entity, an audit log —: once it has run, the entity is no longer scheduled for deletion but for an update.
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

            $now = $this->clock->now();
            $entity->markDeleted($now);
            $manager->persist($entity);
            $work->scheduleExtraUpdate($entity, ['deletedAt' => [null, $now]]);
            $work->setOriginalEntityProperty(spl_object_id($entity), 'deletedAt', $now);
        }
    }
}
