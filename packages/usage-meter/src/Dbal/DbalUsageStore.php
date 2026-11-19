<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\UsageMeter\Counter;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Increment;
use Phil\UsageMeter\UsageStore;
use Throwable;
use UnexpectedValueException;

/**
 * The counters in two tables of a Doctrine DBAL connection: one row per subject, meter and counter start, and one per
 * idempotency key, instants kept as Unix timestamps.
 *
 * An increment is one transaction. The key's row is inserted unless it is there, then the counter's row at zero
 * unless it is there — `ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR
 * IGNORE` on SQLite, so that a conflict never aborts the transaction —, then one `UPDATE` adds the amount where the
 * sum stays within the ceiling. That statement is the atomic step: the database locks the row, so two increments at
 * the same moment are both counted or one is refused. A duplicate key or a refused amount rolls the transaction back,
 * leaving nothing behind. Other platforms are refused at the first increment.
 *
 * The key's row keeps the counter it went to, the amount it counted and what was given back of it. A refund is one
 * transaction too: the key's row is read and locked (`SELECT … FOR UPDATE`; SQLite, which has a single writer, needs no
 * lock), then the key's refunded amount and the counter are written, or nothing when there is nothing left to give.
 */
final readonly class DbalUsageStore implements UsageStore
{
    public const string COUNTER_TABLE = 'phil_usage_counter';

    public const string KEY_TABLE = 'phil_usage_key';

    /** @var non-empty-string */
    private string $counterTable;

    /** @var non-empty-string */
    private string $keyTable;

    public function __construct(
        private Connection $connection,
        string $counterTable = self::COUNTER_TABLE,
        string $keyTable = self::KEY_TABLE,
    ) {
        $this->counterTable = $this->tableName($counterTable);
        $this->keyTable = $this->tableName($keyTable);
    }

    /**
     * Adds both tables to a schema that lacks them: the one the ORM generates, through `UsageSchemaListener`, or one a
     * migration builds.
     */
    public function configureSchema(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable($this->counterTable)) {
            $editor->addTable($this->counterTableDefinition());
        }

        if (!$schema->hasTable($this->keyTable)) {
            $editor->addTable($this->keyTableDefinition());
        }

        return $editor->create();
    }

    #[Override]
    public function add(Counter $counter, int $amount, ?int $ceiling = null, ?string $key = null): Increment
    {
        $platform = $this->connection->getDatabasePlatform();
        $start = $counter->start->getTimestamp();
        $expires = $counter->expiresAt->getTimestamp();
        $this->connection->beginTransaction();

        try {
            if (null !== $key && 1 > $this->connection->executeStatement(
                self::insertUnlessPresent($platform, $this->keyTable, ['subject', 'meter', 'idempotency_key', 'starts_at', 'amount', 'refunded', 'expires_at']),
                [$counter->subject, $counter->meter, $key, $start, $amount, 0, $expires],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            )) {
                $this->connection->rollBack();

                return Increment::Duplicate;
            }

            $this->connection->executeStatement(
                self::insertUnlessPresent($platform, $this->counterTable, ['subject', 'meter', 'starts_at', 'used', 'expires_at']),
                [$counter->subject, $counter->meter, $start, 0, $expires],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
            );

            $sql = \sprintf('UPDATE %s SET used = used + ? WHERE subject = ? AND meter = ? AND starts_at = ?', $this->counterTable);
            $parameters = [$amount, $counter->subject, $counter->meter, $start];
            $types = [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER];

            if (null !== $ceiling) {
                $sql .= ' AND used + ? <= ?';
                $parameters[] = $amount;
                $parameters[] = $ceiling;
                $types[] = ParameterType::INTEGER;
                $types[] = ParameterType::INTEGER;
            }

            if (1 > $this->connection->executeStatement($sql, $parameters, $types)) {
                $this->connection->rollBack();

                return Increment::Refused;
            }

            $this->connection->commit();

            return Increment::Recorded;
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }
    }

    #[Override]
    public function refund(string $subject, string $meter, string $key, ?int $amount = null): int
    {
        $this->connection->beginTransaction();

        try {
            $increment = $this->connection->fetchAssociative(
                self::selectKeyForUpdate($this->connection->getDatabasePlatform(), $this->keyTable),
                [$subject, $meter, $key],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
            );
            $given = false === $increment ? 0 : min($amount ?? $this->integer($increment['amount']), $this->integer($increment['amount'])) - $this->integer($increment['refunded']);

            if (false === $increment || $given < 1 || 1 > $this->connection->executeStatement(
                \sprintf('UPDATE %s SET used = used - ? WHERE subject = ? AND meter = ? AND starts_at = ? AND used >= ?', $this->counterTable),
                [$given, $subject, $meter, $this->integer($increment['starts_at']), $given],
                [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER],
            )) {
                $this->connection->rollBack();

                return 0;
            }

            $this->connection->executeStatement(
                \sprintf('UPDATE %s SET refunded = refunded + ? WHERE subject = ? AND meter = ? AND idempotency_key = ?', $this->keyTable),
                [$given, $subject, $meter, $key],
                [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
            );
            $this->connection->commit();

            return $given;
        } catch (Throwable $throwable) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            throw $throwable;
        }
    }

    #[Override]
    public function total(string $subject, string $meter, DateTimeImmutable $from, DateTimeImmutable $before): int
    {
        $total = $this->connection->fetchOne(
            \sprintf('SELECT COALESCE(SUM(used), 0) FROM %s WHERE subject = ? AND meter = ? AND starts_at >= ? AND starts_at < ?', $this->counterTable),
            [$subject, $meter, $from->getTimestamp(), $before->getTimestamp()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER],
        );

        if (!is_numeric($total)) {
            throw new UnexpectedValueException('The sum of the counters is not a number.');
        }

        return (int) $total;
    }

    #[Override]
    public function purge(DateTimeImmutable $now): int
    {
        $forgotten = 0;

        foreach ([$this->counterTable, $this->keyTable] as $table) {
            $forgotten += (int) $this->connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE expires_at <= ?', $table),
                [$now->getTimestamp()],
            );
        }

        return $forgotten;
    }

    /**
     * An insert that does nothing when a row of the same key is there, without aborting the transaction.
     *
     * @param list<string> $columns every column inserted, the three of the key first: subject, meter, then start or key
     */
    public static function insertUnlessPresent(AbstractPlatform $platform, string $table, array $columns): string
    {
        $into = \sprintf('INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', array_map(static fn (): string => '?', $columns)));

        return match (true) {
            $platform instanceof PostgreSQLPlatform => \sprintf('INSERT %s ON CONFLICT (%s) DO NOTHING', $into, implode(', ', \array_slice($columns, 0, 3))),
            $platform instanceof AbstractMySQLPlatform => 'INSERT IGNORE '.$into,
            $platform instanceof SQLitePlatform => 'INSERT OR IGNORE '.$into,
            default => throw InvalidUsage::because(\sprintf('DbalUsageStore runs on PostgreSQL, MySQL, MariaDB and SQLite; not on %s.', $platform::class)),
        };
    }

    /**
     * The read of an idempotency key's row that locks it until the transaction ends; SQLite, which has a single writer,
     * takes no row lock.
     */
    public static function selectKeyForUpdate(AbstractPlatform $platform, string $table): string
    {
        return \sprintf('SELECT starts_at, amount, refunded FROM %s WHERE subject = ? AND meter = ? AND idempotency_key = ?%s', $table, $platform instanceof SQLitePlatform ? '' : ' FOR UPDATE');
    }

    private function integer(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new UnexpectedValueException('A column of an idempotency key is not a number.');
        }

        return (int) $value;
    }

    /**
     * @return non-empty-string
     */
    private function tableName(string $name): string
    {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $name, $matches)) {
            throw InvalidUsage::because(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $name));
        }

        return $matches[0];
    }

    /**
     * One row per counter: its subject, meter and start, its count, and its expiry indexed for the purge.
     */
    private function counterTableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->counterTable)
            ->setColumns(
                Column::editor()->setUnquotedName('subject')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('meter')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('starts_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('used')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::BIGINT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('subject', 'meter', 'starts_at')->create())
            ->setIndexes(Index::editor()->setUnquotedName($this->counterTable.'_expires_at')->setUnquotedColumnNames('expires_at')->create())
            ->create();
    }

    /**
     * One row per idempotency key of a subject and meter: the start of the counter it went to, the amount it counted,
     * what was given back of it, and the counter's expiry.
     */
    private function keyTableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->keyTable)
            ->setColumns(
                Column::editor()->setUnquotedName('subject')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('meter')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('idempotency_key')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('starts_at')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('amount')->setTypeName(Types::BIGINT)->create(),
                Column::editor()->setUnquotedName('refunded')->setTypeName(Types::BIGINT)->setDefaultValue(0)->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::BIGINT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('subject', 'meter', 'idempotency_key')->create())
            ->setIndexes(Index::editor()->setUnquotedName($this->keyTable.'_expires_at')->setUnquotedColumnNames('expires_at')->create())
            ->create();
    }
}
