<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Phil\SearchBundle\Application\Search;
use WeakMap;

/**
 * Keeps the index of Doctrine entities up to date: an entity inserted or updated is indexed, an entity deleted
 * removed, once the flush succeeds, in the order of the flushes. The entities of a flush go to the index together —
 * one transaction of the database table, one request to Meilisearch per index —, and so do its removals.
 *
 * What a flush will do is noted for its entity manager. A deleted entity's place in the indexes is read before the
 * flush, while its identifier is known: Doctrine empties a generated identifier once the row is deleted. A flush that
 * fails closes its entity manager, so what it noted is never applied; clearing the entity manager, or resetting the
 * service between a worker's messages, forgets it too.
 */
final class IndexOnFlushListener
{
    /**
     * By entity manager: an entity to index, or the addresses of a deleted one to remove.
     *
     * @var WeakMap<object, list<object|list<array{string, string}>>>
     */
    private WeakMap $pending;

    public function __construct(
        private readonly Search $search,
    ) {
        $this->pending = new WeakMap();
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $pending = $this->pending[$entityManager] ?? [];

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $entity) {
            $pending[] = $entity;
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $pending[] = $this->search->addressesOf($entity);
        }

        $this->pending[$entityManager] = $pending;
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $pending = $this->pending[$entityManager] ?? [];
        unset($this->pending[$entityManager]);

        // The entities that follow one another go to the index at once, and so do the removals: their order holds.
        $entities = [];
        $removals = [];

        foreach ($pending as $what) {
            if (\is_object($what)) {
                $this->search->removeAt($removals);
                $removals = [];
                $entities[] = $what;
            } else {
                $this->search->index(...$entities);
                $entities = [];

                foreach ($what as $address) {
                    $removals[] = $address;
                }
            }
        }

        $this->search->index(...$entities);
        $this->search->removeAt($removals);
    }

    public function onClear(OnClearEventArgs $event): void
    {
        unset($this->pending[$event->getObjectManager()]);
    }

    /**
     * Forgets what every flush noted, between a worker's messages.
     */
    public function reset(): void
    {
        $this->pending = new WeakMap();
    }
}
