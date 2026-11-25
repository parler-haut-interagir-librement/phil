<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Outbox;

use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use LogicException;
use Phil\Outbox\Contract\Outbox;
use Phil\SearchBundle\Application\Search;
use Stringable;
use WeakMap;

/**
 * Records in the outbox, in the transaction of the flush, what the indexes must learn of it: each entity inserted or
 * updated, by its class and identifier, and each document of an entity deleted, by its index and identifier there. The
 * records commit with the rows or not at all; the relay indexes them afterwards, and tries again while the engine is
 * down. A flush the database rolls back records nothing, so no index ever holds a document of a row that was never
 * written.
 *
 * Only an entity a provider knows is recorded. The records of one entity share an ordering key, so its indexing and its
 * removal are relayed in the order they happened. A deleted entity's documents are read before the flush, while its
 * identifier is known, and recorded once the row is deleted; clearing the entity manager, or resetting the service
 * between a worker's messages, forgets them.
 */
final class OutboxIndexingListener
{
    /**
     * The documents of each entity a flush deletes: its ordering key and its addresses.
     *
     * @var WeakMap<object, array{string, list<array{string, string}>}>
     */
    private WeakMap $removals;

    public function __construct(
        private readonly Search $search,
        private readonly Outbox $outbox,
    ) {
        $this->removals = new WeakMap();
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();

        foreach ($entityManager->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            $addresses = $this->search->addressesOf($entity);

            if ([] !== $addresses) {
                $this->removals[$entity] = [self::orderingKey($entity::class, $this->identifierOf($entityManager, $entity)), $addresses];
            }
        }
    }

    public function postPersist(PostPersistEventArgs $event): void
    {
        $this->recordIndexing($event->getObjectManager(), $event->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $event): void
    {
        $this->recordIndexing($event->getObjectManager(), $event->getObject());
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!isset($this->removals[$entity])) {
            return;
        }

        [$orderingKey, $addresses] = $this->removals[$entity];
        unset($this->removals[$entity]);

        foreach ($addresses as [$index, $document]) {
            $this->outbox->record(RemoveDocument::TYPE, ['index' => $index, 'document' => $document], $orderingKey);
        }
    }

    public function onClear(): void
    {
        $this->removals = new WeakMap();
    }

    /**
     * Forgets the deletions noted, between a worker's messages.
     */
    public function reset(): void
    {
        $this->removals = new WeakMap();
    }

    /**
     * The key the records of one entity share: its class and its identifier, hashed.
     *
     * @param array<string, string|int> $identifier
     */
    public static function orderingKey(string $class, array $identifier): string
    {
        return 'phil_search:'.hash('xxh128', $class."\0".json_encode($identifier, \JSON_THROW_ON_ERROR));
    }

    private function recordIndexing(EntityManagerInterface $entityManager, object $entity): void
    {
        if ([] === $this->search->addressesOf($entity)) {
            return;
        }

        $identifier = $this->identifierOf($entityManager, $entity);
        $payload = ['class' => $entity::class];

        foreach ($identifier as $field => $value) {
            $payload['id_'.$field] = $value;
        }

        $this->outbox->record(IndexEntity::TYPE, $payload, self::orderingKey($entity::class, $identifier));
    }

    /**
     * @return array<string, string|int> the entity's identifier, by field, each value a string or an integer
     */
    private function identifierOf(EntityManagerInterface $entityManager, object $entity): array
    {
        $identifier = [];

        foreach ($entityManager->getClassMetadata($entity::class)->getIdentifierValues($entity) as $field => $value) {
            $identifier[$field] = match (true) {
                \is_int($value), \is_string($value) => $value,
                $value instanceof BackedEnum => $value->value,
                $value instanceof Stringable => (string) $value,
                default => throw new LogicException(\sprintf('The identifier "%s" of %s is neither a string, an integer, an enum nor stringable: the outbox cannot name the entity; index it with phil_search.index_entities: direct.', $field, $entity::class)),
            };
        }

        return $identifier;
    }
}
