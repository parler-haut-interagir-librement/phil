<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Override;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;

/**
 * The records in a table of the application's DBAL connection. `add()` runs on that connection as it is: called inside
 * the transaction of the package's own write, the record commits or rolls back with it. A record already kept under
 * the same type and idempotency key is left alone with `ON CONFLICT DO NOTHING` on PostgreSQL and SQLite and
 * `ON DUPLICATE KEY UPDATE` on MySQL and MariaDB, which never aborts the caller's transaction.
 *
 * A claim selects the candidates, leases them in one `UPDATE` that only takes a record still free, then reads back the
 * records leased under its token: two relays never get the same record, on every platform, without locking rows.
 *
 * The store keeps no state of its own between calls: one instance serves every request of a worker.
 */
final readonly class DbalOutboxStore implements OutboxStore
{
    private const string COLUMNS = 'id, type, payload, ordering_key, idempotency_key, state, attempts, occurred_at, available_at, last_error, settled_at';

    public function __construct(
        private Connection $connection,
        private OutboxTable $table = new OutboxTable(),
    ) {
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function add(OutboxRecord $record): RecordId
    {
        $values = [
            'id' => $record->id->value,
            'type' => $record->type,
            'payload' => $record->payloadJson,
            'ordering_key' => $record->orderingKey,
            'idempotency_key' => $record->idempotencyKey,
            'state' => $record->state->value,
            'attempts' => $record->attempts,
            'occurred_at' => $record->occurredAt->getTimestamp(),
            'available_at' => $record->availableAt->getTimestamp(),
            'last_error' => $record->lastError,
            'settled_at' => $record->settledAt?->getTimestamp(),
        ];

        if (null === $record->idempotencyKey) {
            $this->connection->insert($this->table->name, $values);

            return $record->id;
        }

        if (1 === Rows::int($this->connection->executeStatement($this->insertUnlessKept($this->connection->getDatabasePlatform()), array_values($values)))) {
            return $record->id;
        }

        $kept = $this->connection->fetchOne(\sprintf('SELECT id FROM %s WHERE type = ? AND idempotency_key = ?', $this->table->name), [$record->type, $record->idempotencyKey]);

        return RecordId::fromString(\is_string($kept) ? $kept : '');
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function claim(string $claim, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array
    {
        if ($limit < 1) {
            return [];
        }

        $table = $this->table->name;
        $platform = $this->connection->getDatabasePlatform();
        $candidates = $this->connection->fetchFirstColumn($platform->modifyLimitQuery(\sprintf(
            "SELECT o.position FROM %1\$s o WHERE o.state = 'pending' AND o.available_at <= ? AND (o.lease_until IS NULL OR o.lease_until <= ?)"
            ." AND (o.ordering_key IS NULL OR NOT EXISTS (SELECT 1 FROM %1\$s e WHERE e.ordering_key = o.ordering_key AND e.position < o.position AND e.state NOT IN ('delivered', 'skipped')))"
            .' ORDER BY o.position',
            $table,
        ), $limit), [$now->getTimestamp(), $now->getTimestamp()]);

        if ([] === $candidates) {
            return [];
        }

        $this->connection->executeStatement(
            \sprintf("UPDATE %s SET claimed_by = ?, lease_until = ? WHERE position IN (?) AND state = 'pending' AND (lease_until IS NULL OR lease_until <= ?)", $table),
            [$claim, $leaseUntil->getTimestamp(), $candidates, $now->getTimestamp()],
            [2 => ArrayParameterType::INTEGER],
        );

        return $this->records(\sprintf("SELECT %s FROM %s WHERE claimed_by = ? AND state = 'pending' ORDER BY position", self::COLUMNS, $table), [$claim]);
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function markDelivered(RecordId $id, string $claim, DateTimeImmutable $at): bool
    {
        return $this->settle($id, $claim, "state = 'delivered', settled_at = ?", [$at->getTimestamp()]);
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function markSkipped(RecordId $id, string $claim, DateTimeImmutable $at): bool
    {
        return $this->settle($id, $claim, "state = 'skipped', settled_at = ?", [$at->getTimestamp()]);
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function release(RecordId $id, string $claim, int $attempts, string $error, DateTimeImmutable $retryAt): bool
    {
        return $this->settle($id, $claim, 'attempts = ?, last_error = ?, available_at = ?', [$attempts, $error, $retryAt->getTimestamp()]);
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function markDead(RecordId $id, string $claim, int $attempts, string $error, DateTimeImmutable $at): bool
    {
        return $this->settle($id, $claim, "state = 'dead', attempts = ?, last_error = ?, settled_at = ?", [$attempts, $error, $at->getTimestamp()]);
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function find(RecordId $id): ?OutboxRecord
    {
        return $this->records(\sprintf('SELECT %s FROM %s WHERE id = ?', self::COLUMNS, $this->table->name), [$id->value])[0] ?? null;
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function list(RecordState $state, int $limit, int $offset = 0): array
    {
        if ($limit < 1) {
            return [];
        }

        return $this->records($this->connection->getDatabasePlatform()->modifyLimitQuery(
            \sprintf('SELECT %s FROM %s WHERE state = ? ORDER BY position', self::COLUMNS, $this->table->name),
            $limit,
            max(0, $offset),
        ), [$state->value]);
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function count(RecordState $state): int
    {
        return Rows::int($this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE state = ?', $this->table->name), [$state->value]));
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function revive(RecordId $id, DateTimeImmutable $now): bool
    {
        return 1 === Rows::int($this->connection->executeStatement(
            \sprintf("UPDATE %s SET state = 'pending', attempts = 0, available_at = ?, settled_at = NULL, claimed_by = NULL, lease_until = NULL WHERE id = ? AND state = 'dead'", $this->table->name),
            [$now->getTimestamp(), $id->value],
        ));
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function discard(RecordId $id): bool
    {
        return 1 === Rows::int($this->connection->executeStatement(\sprintf("DELETE FROM %s WHERE id = ? AND state = 'dead'", $this->table->name), [$id->value]));
    }

    /**
     * @throws Exception when the database fails
     */
    #[Override]
    public function purgeDelivered(DateTimeImmutable $before, int $limit): int
    {
        if ($limit < 1) {
            return 0;
        }

        $positions = $this->connection->fetchFirstColumn($this->connection->getDatabasePlatform()->modifyLimitQuery(
            \sprintf("SELECT position FROM %s WHERE state IN ('delivered', 'skipped') AND settled_at < ? ORDER BY position", $this->table->name),
            $limit,
        ), [$before->getTimestamp()]);

        if ([] === $positions) {
            return 0;
        }

        return Rows::int($this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE position IN (?)', $this->table->name),
            [$positions],
            [ArrayParameterType::INTEGER],
        ));
    }

    /**
     * Removes the message from the errors kept with one, keeping the class before it: `RuntimeException: 550 …`
     * becomes `RuntimeException`. The errors of the outbox's own exceptions, whose text names types and keys only,
     * are left as they are. The table is read by position, a batch at a time, so each row is seen once.
     *
     * @param positive-int $batch
     *
     * @return int the errors rewritten
     *
     * @throws Exception when the database fails
     */
    public function clearErrorMessages(int $batch = 500): int
    {
        $table = $this->table->name;
        $select = $this->connection->getDatabasePlatform()->modifyLimitQuery(\sprintf('SELECT position, last_error FROM %s WHERE position > ? AND last_error LIKE ? ORDER BY position', $table), $batch);
        $update = \sprintf('UPDATE %s SET last_error = ? WHERE position = ? AND last_error = ?', $table);
        $cleared = 0;
        $after = 0;

        do {
            $rows = $this->connection->fetchAllAssociative($select, [$after, '%: %']);

            foreach ($rows as $row) {
                $after = Rows::int($row['position'] ?? null);
                $error = Rows::text($row['last_error'] ?? null);

                if (!str_starts_with($error, OutboxRecord::OWN_EXCEPTIONS)) {
                    $cleared += Rows::int($this->connection->executeStatement($update, [explode(': ', $error, 2)[0], $after, $error]));
                }
            }
        } while (\count($rows) === $batch);

        return $cleared;
    }

    /**
     * The insert that adds nothing when a record of the same type and idempotency key is kept.
     */
    public function insertUnlessKept(AbstractPlatform $platform): string
    {
        $insert = \sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table->name, self::COLUMNS, implode(', ', array_fill(0, 11, '?')));

        return $platform instanceof AbstractMySQLPlatform
            ? $insert.' ON DUPLICATE KEY UPDATE id = id'
            : $insert.' ON CONFLICT (type, idempotency_key) DO NOTHING';
    }

    /**
     * @param non-empty-string $set
     * @param list<int|string> $values
     *
     * @throws Exception when the database fails
     */
    private function settle(RecordId $id, string $claim, string $set, array $values): bool
    {
        return 1 === Rows::int($this->connection->executeStatement(
            \sprintf("UPDATE %s SET %s, claimed_by = NULL, lease_until = NULL WHERE id = ? AND claimed_by = ? AND state = 'pending'", $this->table->name, $set),
            [...$values, $id->value, $claim],
        ));
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<OutboxRecord>
     *
     * @throws Exception when the database fails
     */
    private function records(string $sql, array $parameters): array
    {
        return array_map($this->record(...), $this->connection->fetchAllAssociative($sql, $parameters));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidRecord when a column is not what the table keeps
     */
    private function record(array $row): OutboxRecord
    {
        $state = RecordState::tryFrom(Rows::text($row['state'] ?? null)) ?? throw InvalidRecord::because('A record of the outbox table has no known state.');
        $settledAt = $row['settled_at'] ?? null;

        return new OutboxRecord(
            RecordId::fromString(Rows::text($row['id'] ?? null)),
            Rows::text($row['type'] ?? null),
            Rows::text($row['payload'] ?? null),
            Rows::nullableText($row['ordering_key'] ?? null),
            Rows::nullableText($row['idempotency_key'] ?? null),
            $this->moment($row['occurred_at'] ?? null),
            $this->moment($row['available_at'] ?? null),
            $state,
            Rows::int($row['attempts'] ?? null),
            Rows::nullableText($row['last_error'] ?? null),
            null === $settledAt ? null : $this->moment($settledAt),
        );
    }

    private function moment(mixed $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.Rows::int($seconds))->setTimezone(new DateTimeZone('UTC'));
    }
}
