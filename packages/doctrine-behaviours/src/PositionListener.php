<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Phil\DoctrineBehaviours\Contract\Positionable;

/**
 * Sends `Positionable` entities to the end of their group as they are flushed: one inserted without a position, one
 * whose position was set back to null, and one that changed group while its position stayed. A position given is
 * kept. One query per group settles it, on the table itself, so that rows the SQL filters hide still count; entities
 * of the same flush follow each other.
 */
final readonly class PositionListener
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();
        /** @var array<string, array<string, int>> $next the next position of each group met in this flush, by table */
        $next = [];

        foreach ($work->getScheduledEntityInsertions() as $record) {
            if ($record instanceof Positionable && null === $record->position()) {
                $this->sendToEnd($manager, $record, $next);
            }
        }

        foreach ($work->getScheduledEntityUpdates() as $record) {
            if (!$record instanceof Positionable) {
                continue;
            }

            $change = $work->getEntityChangeSet($record);

            if (null === $record->position() || (!\array_key_exists(Positionable::POSITION_FIELD, $change) && [] !== array_intersect($record->positionGroup(), array_keys($change)))) {
                $this->sendToEnd($manager, $record, $next);
            }
        }
    }

    /**
     * @param array<string, array<string, int>> $next
     */
    private function sendToEnd(EntityManagerInterface $manager, Positionable $record, array &$next): void
    {
        $metadata = $manager->getClassMetadata($record::class);
        $table = Scope::tableOf($manager, $metadata, Positionable::POSITION_FIELD);
        $scope = Scope::of($manager, $metadata, $record, $record->positionGroup());
        $key = $scope->key;

        if (!isset($next[$table][$key])) {
            $next[$table][$key] = 1;

            if (!$scope->reachesNothing) {
                $column = $manager->getConfiguration()->getQuoteStrategy()->getColumnName(Positionable::POSITION_FIELD, $metadata, $manager->getConnection()->getDatabasePlatform());
                $last = $manager->getConnection()->fetchOne(\sprintf('SELECT MAX(%s) FROM %s WHERE %s', $column, $table, $scope->where), $scope->parameters, $scope->types);
                $next[$table][$key] = is_numeric($last) ? (int) $last + 1 : 1;
            }
        }

        $record->placeAt($next[$table][$key]++);
        $manager->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $record);
    }
}
