<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Exception\InvalidIncident;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\Restriction;

/**
 * The activations in `phil_incident_activation`, through DBAL. Lifting and extending are one conditional `UPDATE`
 * each, on the rows not lifted yet, so that the database decides between two processes. A row that no longer reads —
 * edited by hand into something the library refuses — is left out rather than failing every check.
 */
final readonly class DbalActivationStore implements ActivationStore
{
    private const string COLUMNS = 'id, preset, restrictions, started_at, ends_at, started_by, reason, lifted_at, lifted_by, lift_reason';

    private const array MOMENTS = ['started_at' => Types::DATETIME_UTC_IMMUTABLE, 'ends_at' => Types::DATETIME_UTC_IMMUTABLE, 'lifted_at' => Types::DATETIME_UTC_IMMUTABLE];

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Activation $activation): void
    {
        $this->connection->insert(IncidentTables::ACTIVATIONS, [
            'id' => $activation->id,
            'preset' => $activation->preset,
            'restrictions' => implode(',', $activation->restrictionNames()),
            'started_at' => $activation->startedAt,
            'ends_at' => $activation->endsAt,
            'started_by' => $activation->startedBy?->id,
            'reason' => $activation->reason,
            'lifted_at' => $activation->liftedAt,
            'lifted_by' => $activation->liftedBy?->id,
            'lift_reason' => $activation->liftReason?->value,
        ], self::MOMENTS);
    }

    #[Override]
    public function find(string $id): ?Activation
    {
        $row = $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM '.IncidentTables::ACTIVATIONS.' WHERE id = ?', [$id]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function unliftedEndingAfter(DateTimeImmutable $now): array
    {
        return $this->hydrateAll($this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM '.IncidentTables::ACTIVATIONS.' WHERE lifted_at IS NULL AND ends_at > ? ORDER BY started_at, id',
            [$now],
            [Types::DATETIME_UTC_IMMUTABLE],
        ));
    }

    #[Override]
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array
    {
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery(
            'SELECT id FROM '.IncidentTables::ACTIVATIONS.' WHERE lifted_at IS NULL AND ends_at <= ? ORDER BY ends_at, id',
            $limit,
        );

        return array_values(array_filter($this->connection->fetchFirstColumn($sql, [$now], [Types::DATETIME_UTC_IMMUTABLE]), is_string(...)));
    }

    #[Override]
    public function lift(string $id, DateTimeImmutable $at, ?Actor $by, LiftReason $reason): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'UPDATE '.IncidentTables::ACTIVATIONS.' SET lifted_at = ?, lifted_by = ?, lift_reason = ? WHERE id = ? AND lifted_at IS NULL',
            [$at, $by?->id, $reason->value, $id],
            [Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
        );
    }

    #[Override]
    public function extend(string $id, DateTimeImmutable $endsAt): bool
    {
        return 1 === (int) $this->connection->executeStatement(
            'UPDATE '.IncidentTables::ACTIVATIONS.' SET ends_at = ? WHERE id = ? AND lifted_at IS NULL',
            [$endsAt, $id],
            [Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING],
        );
    }

    #[Override]
    public function history(int $limit, int $offset = 0): array
    {
        $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery(
            'SELECT '.self::COLUMNS.' FROM '.IncidentTables::ACTIVATIONS.' ORDER BY started_at DESC, id DESC',
            $limit,
            $offset,
        );

        return $this->hydrateAll($this->connection->fetchAllAssociative($sql));
    }

    #[Override]
    public function ofActor(Actor $actor): array
    {
        return $this->hydrateAll($this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM '.IncidentTables::ACTIVATIONS.' WHERE started_by = ? OR lifted_by = ? ORDER BY started_at DESC, id DESC',
            [$actor->id, $actor->id],
        ));
    }

    #[Override]
    public function forgetActor(Actor $actor): int
    {
        return $this->connection->transactional(function () use ($actor): int {
            $ids = $this->connection->fetchFirstColumn('SELECT id FROM '.IncidentTables::ACTIVATIONS.' WHERE started_by = ? OR lifted_by = ?', [$actor->id, $actor->id]);
            $this->connection->executeStatement('UPDATE '.IncidentTables::ACTIVATIONS.' SET started_by = NULL WHERE started_by = ?', [$actor->id]);
            $this->connection->executeStatement('UPDATE '.IncidentTables::ACTIVATIONS.' SET lifted_by = NULL WHERE lifted_by = ?', [$actor->id]);

            return \count($ids);
        });
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Activation>
     */
    private function hydrateAll(array $rows): array
    {
        return array_values(array_filter(array_map($this->hydrate(...), $rows)));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ?Activation
    {
        try {
            $restrictions = array_map(Restriction::named(...), explode(',', $this->text($row, 'restrictions') ?? ''));
            $liftReason = $this->text($row, 'lift_reason');

            return new Activation(
                $this->text($row, 'id') ?? '',
                $this->text($row, 'preset') ?? '',
                $restrictions,
                $this->instant($row['started_at'] ?? null),
                $this->instant($row['ends_at'] ?? null),
                $this->actor($row, 'started_by'),
                $this->text($row, 'reason'),
                null === ($row['lifted_at'] ?? null) ? null : $this->instant($row['lifted_at']),
                $this->actor($row, 'lifted_by'),
                null === $liftReason ? null : LiftReason::tryFrom($liftReason) ?? throw InvalidIncident::because('No such reason.'),
            );
        } catch (InvalidIncident) {
            return null;
        }
    }

    /**
     * A moment of a column the table keeps not null, read in UTC.
     */
    private function instant(mixed $value): DateTimeImmutable
    {
        $moment = $this->connection->convertToPHPValue($value, Types::DATETIME_UTC_IMMUTABLE);
        \assert($moment instanceof DateTimeImmutable);

        return $moment;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function actor(array $row, string $column): ?Actor
    {
        $id = $this->text($row, $column);

        return null === $id ? null : Actor::of($id);
    }
}
