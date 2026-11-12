<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\Contract\OwnerResolver;

/**
 * Gives a persisted `Ownable` entity the owner the resolver names, unless it was given one. An owner never changes
 * on its own: an update leaves it alone, and `transferOwner()` is the application's to call.
 */
final readonly class OwnerListener
{
    public function __construct(
        private OwnerResolver $resolver,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof Ownable) {
            return;
        }

        $owner = $this->resolver->current();

        if ($owner instanceof OwnerId) {
            $entity->claimOwner($owner);
        }
    }
}
