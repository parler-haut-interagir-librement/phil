<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Outbox;

use Doctrine\Persistence\ManagerRegistry;
use Phil\SearchBundle\Application\Search;

/**
 * Applies what the outbox relays: an entity read again and indexed as it now is — nothing when it is gone, its removal
 * following —, a document removed. A failure, an engine down, leaves the record to the relay, which tries again.
 */
final readonly class OutboxIndexer
{
    public function __construct(
        private Search $search,
        private ManagerRegistry $doctrine,
    ) {
    }

    public function index(IndexEntity $event): void
    {
        $entity = $this->doctrine->getManagerForClass($event->class)?->find($event->class, $event->identifier);

        if (null !== $entity) {
            $this->search->index($entity);
        }
    }

    public function remove(RemoveDocument $event): void
    {
        $this->search->forget($event->index, $event->document);
    }
}
