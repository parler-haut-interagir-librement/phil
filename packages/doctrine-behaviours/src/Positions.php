<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Phil\DoctrineBehaviours\Contract\Positionable;
use Phil\DoctrineBehaviours\Exception\InvalidScope;

/**
 * Moves a stored `Positionable` record within its group: before or after another record of the group, first or last.
 * Each move is one transaction on the table itself: the row of the record it is placed against — every row of the
 * group, for a move to the first or last place — is locked by a write that changes nothing, then read; the records from there on step one place down, and the record takes the place.
 * Records the SQL filters hide move too, and the listeners do not run. The loaded records of the group follow; one
 * whose position was changed in memory and not flushed keeps that change.
 */
final readonly class Positions
{
    public function __construct(
        private EntityManagerInterface $manager,
    ) {
    }

    /**
     * @throws InvalidScope when either record is not stored, or the two are in different groups or tables
     */
    public function moveBefore(Positionable $record, Positionable $other): void
    {
        $this->moveAgainst($record, $other, 0);
    }

    /**
     * @throws InvalidScope when either record is not stored, or the two are in different groups or tables
     */
    public function moveAfter(Positionable $record, Positionable $other): void
    {
        $this->moveAgainst($record, $other, 1);
    }

    /**
     * @throws InvalidScope when the record is not stored
     */
    public function moveFirst(Positionable $record): void
    {
        $place = $this->place($record);
        $this->connection()->transactional(function (Connection $connection) use ($place): void {
            $this->lockGroup($connection, $place);
            $first = $connection->fetchOne(\sprintf('SELECT MIN(%s) FROM %s WHERE %s AND NOT (%s)', $place->column, $place->table, $place->scope->where, $place->identity), [...$place->scope->parameters, ...$place->identifier], [...$place->scope->types, ...$place->identifierTypes]);

            if (is_numeric($first)) {
                $this->take($connection, $place, (int) $first, true);
            }
        });
    }

    /**
     * @throws InvalidScope when the record is not stored
     */
    public function moveLast(Positionable $record): void
    {
        $place = $this->place($record);
        $this->connection()->transactional(function (Connection $connection) use ($place): void {
            $this->lockGroup($connection, $place);
            $last = $connection->fetchOne(\sprintf('SELECT MAX(%s) FROM %s WHERE %s AND NOT (%s)', $place->column, $place->table, $place->scope->where, $place->identity), [...$place->scope->parameters, ...$place->identifier], [...$place->scope->types, ...$place->identifierTypes]);

            if (is_numeric($last)) {
                $this->take($connection, $place, (int) $last + 1, false);
            }
        });
    }

    private function moveAgainst(Positionable $record, Positionable $other, int $offset): void
    {
        $place = $this->place($record);
        $against = $this->place($other);

        if ($place->table !== $against->table || $place->scope->key !== $against->scope->key) {
            throw InvalidScope::otherGroup($record::class);
        }

        if ($place->identifier === $against->identifier) {
            return;
        }

        $this->connection()->transactional(function (Connection $connection) use ($place, $against, $offset): void {
            // A write that changes nothing locks the row on every platform, where FOR UPDATE is not on all of them.
            $connection->executeStatement(\sprintf('UPDATE %1$s SET %2$s = %2$s WHERE %3$s', $against->table, $against->column, $against->identity), $against->identifier, $against->identifierTypes);
            $position = $connection->fetchOne(\sprintf('SELECT %s FROM %s WHERE %s', $against->column, $against->table, $against->identity), $against->identifier, $against->identifierTypes);

            if (is_numeric($position)) {
                $this->take($connection, $place, (int) $position + $offset, true);
            }
        });
    }

    /**
     * Locks every row of the record's group before its first or last position is read, so that two moves to the
     * same end wait for each other instead of reading the same position. A write that changes nothing locks the rows
     * on every platform, where FOR UPDATE is not on all of them, and the read that follows sees what the other move
     * committed.
     */
    private function lockGroup(Connection $connection, Place $place): void
    {
        $connection->executeStatement(
            \sprintf('UPDATE %1$s SET %2$s = %2$s WHERE %3$s', $place->table, $place->column, $place->scope->where),
            $place->scope->parameters,
            $place->scope->types,
        );
    }

    /**
     * Gives the record a position, the others from there on stepping one place down first when asked. The record
     * steps down with them when it is among them, and the second write sets it.
     */
    private function take(Connection $connection, Place $place, int $position, bool $shift): void
    {
        if ($shift) {
            $connection->executeStatement(
                \sprintf('UPDATE %1$s SET %2$s = %2$s + 1 WHERE %3$s AND %2$s >= ?', $place->table, $place->column, $place->scope->where),
                [...$place->scope->parameters, $position],
                [...$place->scope->types, 'integer'],
            );
        }

        $connection->executeStatement(
            \sprintf('UPDATE %s SET %s = ? WHERE %s', $place->table, $place->column, $place->identity),
            [$position, ...$place->identifier],
            ['integer', ...$place->identifierTypes],
        );

        $this->follow($place, $position, $shift);
    }

    /**
     * Brings the loaded records of the group in line with the table.
     */
    private function follow(Place $place, int $position, bool $shift): void
    {
        $work = $this->manager->getUnitOfWork();

        foreach ($work->getIdentityMap()[$place->metadata->rootEntityName] ?? [] as $loaded) {
            if (!$loaded instanceof Positionable) {
                continue;
            }

            $original = $work->getOriginalEntityData($loaded)[Positionable::POSITION_FIELD] ?? null;
            $current = $loaded->position();

            if ($loaded === $place->record) {
                $new = $position;
            } elseif ($shift && null !== $current && $current === $original && $current >= $position && $place->scope->key === Scope::of($this->manager, $this->manager->getClassMetadata($loaded::class), $loaded, $loaded->positionGroup())->key) {
                $new = $current + 1;
            } else {
                continue;
            }

            if ($loaded === $place->record || $current === $original) {
                $loaded->placeAt($new);
            }

            $work->setOriginalEntityProperty(spl_object_id($loaded), Positionable::POSITION_FIELD, $new);
        }
    }

    /**
     * Where a stored record is: its table, its column, its group, and the condition that finds its row.
     *
     * @throws InvalidScope when the record or its group is not stored
     */
    private function place(Positionable $record): Place
    {
        $metadata = $this->manager->getClassMetadata($record::class);
        $work = $this->manager->getUnitOfWork();

        if (!$work->isInIdentityMap($record) || $work->isScheduledForInsert($record)) {
            throw InvalidScope::notStored($record::class);
        }

        $scope = Scope::of($this->manager, $metadata, $record, $record->positionGroup());

        if ($scope->reachesNothing) {
            throw InvalidScope::notStored($record::class);
        }

        $platform = $this->connection()->getDatabasePlatform();
        $quotes = $this->manager->getConfiguration()->getQuoteStrategy();
        $identifier = $work->getEntityIdentifier($record);
        $conditions = [];
        $values = [];
        $types = [];

        foreach ($metadata->getIdentifierFieldNames() as $field) {
            if (!isset($metadata->fieldMappings[$field])) {
                throw InvalidScope::compositeTarget($record::class, $field);
            }

            $conditions[] = $quotes->getColumnName($field, $metadata, $platform).' = ?';
            $values[] = $identifier[$field] ?? null;
            $types[] = $metadata->fieldMappings[$field]->type;
        }

        return new Place(
            $record,
            $metadata,
            Scope::tableOf($this->manager, $metadata, Positionable::POSITION_FIELD),
            $quotes->getColumnName(Positionable::POSITION_FIELD, $metadata, $platform),
            $scope,
            implode(' AND ', $conditions),
            $values,
            $types,
        );
    }

    private function connection(): Connection
    {
        return $this->manager->getConnection();
    }
}
