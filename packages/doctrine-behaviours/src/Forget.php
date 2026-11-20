<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Closure;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What an eraser does once its `UPDATE`s are committed, so that no entity manager gives the old value back: it evicts
 * the second-level cache region of each class it changed, and detaches the managed entities that still hold the value.
 *
 * @internal
 */
final class Forget
{
    /**
     * @param list<EntityManagerInterface>                      $managers the managers on the connection written
     * @param list<array{EntityManagerInterface, class-string}> $classes  the classes changed, by manager
     * @param Closure(object): bool                             $stale    whether a managed entity holds the old value
     */
    public static function after(array $managers, array $classes, Closure $stale): void
    {
        foreach ($classes as [$manager, $class]) {
            $manager->getCache()?->evictEntityRegion($class);
        }

        foreach ($managers as $manager) {
            foreach ($manager->getUnitOfWork()->getIdentityMap() as $entities) {
                foreach ($entities as $entity) {
                    if ($stale($entity)) {
                        $manager->detach($entity);
                    }
                }
            }
        }
    }
}
