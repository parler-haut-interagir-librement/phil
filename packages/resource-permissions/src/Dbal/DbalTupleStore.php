<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Dbal;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleScope;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\UnscopedTuples;
use Phil\ResourcePermissions\Partition;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\SinglePartition;
use Phil\ResourcePermissions\Subject;
use Psr\Clock\ClockInterface;
use UnexpectedValueException;

/**
 * The tuples in one table of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite: one row per
 * partition, object, relation and subject, with its expiry as a Unix timestamp or null.
 *
 * Every statement names the partition of the scope, asked for on each call: a tuple of one partition never matches,
 * lists, forgets or purges in another, and a scope without a partition fails the call before any statement. The
 * primary key, partition first, reads the tuples of an object — a check —, a second index the tuples of a subject — a
 * listing, or forgetting a member —, a third the expired tuples. Identifiers compare byte by byte: the columns take the
 * binary collation of MySQL and MariaDB and the `C` collation of PostgreSQL, so that `member:Ann` is never
 * `member:ann`. Every read leaves out the tuples that have expired by the clock; `purgeExpired()` deletes them.
 */
final readonly class DbalTupleStore implements TupleStore
{
    public const string TABLE = 'phil_relation_tuple';

    public const int MAX_MATCHING = 256;

    private const string COLUMNS = 'object_kind, object_id, relation, subject_kind, subject_id, subject_relation, expires_at';

    /** @var non-empty-string */
    private string $table;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        string $table = self::TABLE,
        private TupleScope $scope = new SinglePartition(),
    ) {
        if (1 !== preg_match('/^[a-z_][a-z0-9_]{0,62}$/D', $table, $matches)) {
            throw InvalidRelation::because(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $table));
        }

        $this->table = $matches[0];
    }

    /**
     * Adds the table to a schema that lacks it: the one the ORM generates, through `TupleSchemaListener`, or one a
     * migration builds.
     */
    public function configureSchema(Schema $schema): Schema
    {
        if ($schema->hasTable($this->table)) {
            return $schema;
        }

        return $schema->edit()->addTable($this->tableDefinition())->create();
    }

    /**
     * The statements that bring the connection's table to this version's definition: the table when it is missing,
     * the changes when it is older — the `scope` column, whose default puts every row already there in the default
     * partition, and the key and indexes that start with it —, nothing when it is current. A migration runs them.
     *
     * @return list<string>
     */
    public function schemaUpdate(): array
    {
        $manager = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();

        if (!$manager->tableExists($this->table)) {
            return $platform->getCreateTableSQL($this->tableDefinition());
        }

        $diff = $manager->createComparator()->compareTables($manager->introspectTableByUnquotedName($this->table), $this->tableDefinition());

        return $diff->isEmpty() ? [] : $platform->getAlterTableSQL($diff);
    }

    #[Override]
    public function update(array $delete, array $write): void
    {
        $partition = $this->partition();

        $this->connection->transactional(function (Connection $connection) use ($partition, $delete, $write): void {
            foreach ([...$delete, ...$write] as $tuple) {
                $connection->executeStatement(
                    \sprintf('DELETE FROM %s WHERE scope = ? AND object_kind = ? AND object_id = ? AND relation = ? AND subject_kind = ? AND subject_id = ? AND subject_relation = ?', $this->table),
                    [$partition, ...$this->key($tuple)],
                );
            }

            foreach ($write as $tuple) {
                $connection->executeStatement(
                    \sprintf('INSERT INTO %s (scope, %s) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $this->table, self::COLUMNS),
                    [$partition, ...$this->key($tuple), $tuple->expiresAt?->getTimestamp()],
                    [...array_pad([], 7, ParameterType::STRING), null === $tuple->expiresAt ? ParameterType::NULL : ParameterType::INTEGER],
                );
            }
        });
    }

    #[Override]
    public function matching(ResourceReference $object, array $relations, array $subjects): array
    {
        if (\count($subjects) > self::MAX_MATCHING || \count($relations) > self::MAX_MATCHING) {
            throw InvalidRelation::because(\sprintf('A match reads at most %d relations and %d subjects.', self::MAX_MATCHING, self::MAX_MATCHING));
        }

        $conditions = [];
        $parameters = [$this->partition(), $object->key, $object->id, $relations, $this->now()];
        $types = [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ArrayParameterType::STRING, ParameterType::INTEGER];

        foreach ($subjects as $subject) {
            $conditions[] = '(subject_kind = ? AND subject_id = ? AND subject_relation = ?)';
            $parameters[] = $subject->reference->key;
            $parameters[] = $subject->reference->id;
            $parameters[] = $subject->relation ?? '';
            $types[] = ParameterType::STRING;
            $types[] = ParameterType::STRING;
            $types[] = ParameterType::STRING;
        }

        return $this->tuples(
            \sprintf('SELECT %s FROM %s WHERE scope = ? AND object_kind = ? AND object_id = ? AND relation IN (?) AND (expires_at IS NULL OR expires_at > ?) AND (%s) ORDER BY relation, subject_kind, subject_id, subject_relation', self::COLUMNS, $this->table, implode(' OR ', $conditions)),
            $parameters,
            $types,
        );
    }

    #[Override]
    public function subjects(ResourceReference $object, string $relation, int $limit, bool $setsOnly = false): array
    {
        return $this->tuples(
            \sprintf(
                'SELECT %s FROM %s WHERE scope = ? AND object_kind = ? AND object_id = ? AND relation = ? AND (expires_at IS NULL OR expires_at > ?)%s ORDER BY subject_kind, subject_id, subject_relation',
                self::COLUMNS,
                $this->table,
                $setsOnly ? " AND subject_relation <> ''" : '',
            ),
            [$this->partition(), $object->key, $object->id, $relation, $this->now()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
            $limit,
        );
    }

    #[Override]
    public function objects(string $kind, string $relation, Subject $subject, int $limit): array
    {
        return $this->tuples(
            \sprintf('SELECT %s FROM %s WHERE scope = ? AND subject_kind = ? AND subject_id = ? AND subject_relation = ? AND object_kind = ? AND relation = ? AND (expires_at IS NULL OR expires_at > ?) ORDER BY object_id', self::COLUMNS, $this->table),
            [$this->partition(), $subject->reference->key, $subject->reference->id, $subject->relation ?? '', $kind, $relation, $this->now()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
            $limit,
        );
    }

    #[Override]
    public function forget(ResourceReference $object): int
    {
        $partition = $this->partition();

        return $this->connection->transactional(fn (Connection $connection): int => $this->count($connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE scope = ? AND object_kind = ? AND object_id = ?', $this->table),
            [$partition, $object->key, $object->id],
        )) + $this->count($connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE scope = ? AND subject_kind = ? AND subject_id = ?', $this->table),
            [$partition, $object->key, $object->id],
        )));
    }

    #[Override]
    public function purgeExpired(): int
    {
        return $this->count($this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE scope = ? AND expires_at <= ?', $this->table),
            [$this->partition(), $this->now()],
            [ParameterType::STRING, ParameterType::INTEGER],
        ));
    }

    /**
     * @return list<string>
     */
    private function key(RelationTuple $tuple): array
    {
        return [$tuple->object->key, $tuple->object->id, $tuple->relation, $tuple->subject->reference->key, $tuple->subject->reference->id, $tuple->subject->relation ?? ''];
    }

    /**
     * The partition of this call, by the rule of `Partition`.
     *
     * @throws UnscopedTuples when the scope has none
     */
    private function partition(): string
    {
        return Partition::check($this->scope->partition());
    }

    private function count(int|string $rows): int
    {
        return (int) $rows;
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException('A column of a relation tuple is not text.');
        }

        return $value;
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    /**
     * @param list<mixed>                            $parameters
     * @param list<ParameterType|ArrayParameterType> $types
     *
     * @return list<RelationTuple>
     */
    private function tuples(string $sql, array $parameters, array $types, ?int $limit = null): array
    {
        if (null !== $limit) {
            $sql = $this->connection->getDatabasePlatform()->modifyLimitQuery($sql, $limit);
        }

        $tuples = [];

        foreach ($this->connection->fetchAllAssociative($sql, $parameters, $types) as $row) {
            $subject = ResourceReference::of($this->text($row['subject_kind']), $this->text($row['subject_id']));
            $relation = $this->text($row['subject_relation']);
            $expires = $row['expires_at'];

            $tuples[] = new RelationTuple(
                ResourceReference::of($this->text($row['object_kind']), $this->text($row['object_id'])),
                $this->text($row['relation']),
                match (true) {
                    '' !== $relation => Subject::set($subject, $relation),
                    Subject::EVERY === $subject->id => Subject::every($subject->key),
                    default => Subject::of($subject),
                },
                null === $expires ? null : new DateTimeImmutable('@'.(is_numeric($expires) ? (int) $expires : throw new UnexpectedValueException('The expiry of a relation tuple is not a number.'))),
            );
        }

        return $tuples;
    }

    /**
     * A text column that compares byte by byte on the connection's platform. On MySQL and MariaDB, a column of names —
     * kinds and relations, lowercase ASCII by their rule — takes the ASCII character set, a byte per character, so that
     * the primary key stays within the 3,072 bytes an index key may hold; any other takes `utf8mb4`.
     *
     * @param non-empty-string $name
     */
    private function textColumn(string $name, int $length, bool $ascii = false): ColumnEditor
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $column->setCharset($ascii ? 'ascii' : 'utf8mb4')->setCollation($ascii ? 'ascii_bin' : 'utf8mb4_bin');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $column->setCollation('C');
        }

        return $column;
    }

    private function tableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->table)
            ->setColumns(
                $this->textColumn('scope', Partition::MAX_LENGTH)->setDefaultValue(Partition::DEFAULT)->create(),
                $this->textColumn('object_kind', 64, true)->create(),
                $this->textColumn('object_id', 255)->create(),
                $this->textColumn('relation', 64, true)->create(),
                $this->textColumn('subject_kind', 64, true)->create(),
                $this->textColumn('subject_id', 255)->create(),
                $this->textColumn('subject_relation', 64, true)->create(),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('scope', 'object_kind', 'object_id', 'relation', 'subject_kind', 'subject_id', 'subject_relation')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName($this->table.'_subject')->setUnquotedColumnNames('scope', 'subject_kind', 'subject_id', 'subject_relation', 'object_kind', 'relation')->create(),
                Index::editor()->setUnquotedName($this->table.'_expires_at')->setUnquotedColumnNames('scope', 'expires_at')->create(),
            )
            ->create();
    }
}
