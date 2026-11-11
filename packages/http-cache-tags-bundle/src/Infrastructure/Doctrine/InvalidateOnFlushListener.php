<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Phil\HttpCacheTagsBundle\Application\Invalidator;

/**
 * Invalidates the tags of every entity a flush inserts, updates or deletes, as its resolvers name them: the responses
 * that show it, or list it, are purged once the request or the command ends.
 */
final readonly class InvalidateOnFlushListener
{
    public function __construct(
        private Invalidator $invalidator,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $unitOfWork = $event->getObjectManager()->getUnitOfWork();

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            $this->invalidator->invalidateEntity($entity);
        }
    }
}
