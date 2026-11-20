<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Phil\DoctrineBehaviours\Contract\SoftDeletable;
use Psr\Clock\ClockInterface;

/**
 * Deletes a `SoftDeletable` entity for good. A first `remove()` of such an entity keeps its whole row, every column
 * as it was, with only `deleted_at` set: what an erasure must not count on. `purge()` deletes the row in one flush,
 * whether the entity was soft-deleted before or not, and the listeners of the flush see a deletion.
 */
final readonly class SoftDeletes
{
    public function __construct(
        private ManagerRegistry|EntityManagerInterface $managers,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Deletes the row of a managed entity, and flushes what else is pending with it.
     *
     * @throws LogicException when no entity manager of the registry manages the entity's class
     */
    public function purge(SoftDeletable $entity): void
    {
        $manager = $this->manager($entity);

        if (!$entity->isDeleted()) {
            $entity->markDeleted($this->clock->now());
        }

        $manager->remove($entity);
        $manager->flush();
    }

    private function manager(SoftDeletable $entity): EntityManagerInterface
    {
        if ($this->managers instanceof EntityManagerInterface) {
            return $this->managers;
        }

        $manager = $this->managers->getManagerForClass($entity::class);

        return $manager instanceof EntityManagerInterface ? $manager : throw new LogicException(\sprintf('No entity manager manages %s.', $entity::class));
    }
}
