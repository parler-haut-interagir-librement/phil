<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Phil\ReferencesBundle\Application\References;
use Phil\Resource\ResourceReference;
use WeakMap;

/**
 * Keeps the usages of Doctrine entities up to date, flush by flush: a source inserted or updated has its links
 * collected again once the flush succeeds, a source deleted has them forgotten; and, unless told otherwise, a target
 * still used by a source that stays refuses its deletion, which aborts the flush.
 *
 * What a flush will do is noted for its entity manager. A deleted source's reference is read before the flush, while
 * its identifier is known: Doctrine empties a generated identifier once the row is deleted. A flush that fails closes
 * its entity manager, so what it noted is never applied; clearing the entity manager, or resetting the service between
 * a worker's messages, forgets it too.
 */
final class ReferenceTrackingListener
{
    /**
     * By entity manager, in the order of the flushes: an entity to refresh, or the reference of a source to forget.
     *
     * @var WeakMap<object, list<object>>
     */
    private WeakMap $pending;

    public function __construct(
        private readonly References $references,
        private readonly bool $blockDeletion = true,
    ) {
        $this->pending = new WeakMap();
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $deleted = $unitOfWork->getScheduledEntityDeletions();
        $leaving = array_filter(array_map($this->references->sourceOf(...), $deleted));

        if ($this->blockDeletion) {
            foreach ($deleted as $entity) {
                $target = $this->references->referenceOf($entity);

                if ($target instanceof ResourceReference) {
                    $this->references->assertUnused($target, $leaving);
                }
            }
        }

        $this->pending[$entityManager] = [
            ...$this->pending[$entityManager] ?? [],
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$leaving,
        ];
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $pending = $this->pending[$entityManager] ?? [];
        unset($this->pending[$entityManager]);

        foreach ($pending as $what) {
            if ($what instanceof ResourceReference) {
                $this->references->forgetSource($what);
            } else {
                $this->references->refresh($what);
            }
        }
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
