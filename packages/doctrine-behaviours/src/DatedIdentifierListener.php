<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Phil\DoctrineBehaviours\Contract\DatedIdentifiable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\UuidV7;

/**
 * Gives a persisted `DatedIdentifiable` entity a UUID v7 of the clock's time, unless it was given one.
 */
final readonly class DatedIdentifierListener
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof DatedIdentifiable && null === $entity->datedId()) {
            $entity->assignDatedId(UuidV7::generate($this->clock->now()));
        }
    }
}
