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
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Psr\Clock\ClockInterface;
use UnexpectedValueException;

/**
 * The tuples in one table of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite: one row per object,
 * relation and subject, with its expiry as a Unix timestamp or null.
 *
 * The primary key reads the tuples of an object — a check —, a second index the tuples of a subject — a listing, or
 * forgetting a member —, a third the expired tuples. Identifiers compare byte by byte: the columns take the binary
 * collation of MySQL and MariaDB and the `C` collation of PostgreSQL, so that `member:Ann` is never `member:ann`.
 * Every read leaves out the tuples that have expired by the clock; `purgeExpired()` deletes them.
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

    #[Override]
    public function update(array $delete, array $write): void
    {
        $this->connection->transactional(function (Connection $connection) use ($delete, $write): void {
            foreach ([...$delete, ...$write] as $tuple) {
                $connection->executeStatement(
                    \sprintf('DELETE FROM %s WHERE object_kind = ? AND object_id = ? AND relation = ? AND subject_kind = ? AND subject_id = ? AND subject_relation = ?', $this->table),
                    $this->key($tuple),
                );
            }

            foreach ($write as $tuple) {
                $connection->executeStatement(
                    \sprintf('INSERT INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?)', $this->table, self::COLUMNS),
                    [...$this->key($tuple), $tuple->expiresAt?->getTimestamp()],
                    [...array_pad([], 6, ParameterType::STRING), null === $tuple->expiresAt ? ParameterType::NULL : ParameterType::INTEGER],
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
        $parameters = [$object->key, $object->id, $relations, $this->now()];
        $types = [ParameterType::STRING, ParameterType::STRING, ArrayParameterType::STRING, ParameterType::INTEGER];

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
            \sprintf('SELECT %s FROM %s WHERE object_kind = ? AND object_id = ? AND relation IN (?) AND (expires_at IS NULL OR expires_at > ?) AND (%s) ORDER BY relation, subject_kind, subject_id, subject_relation', self::COLUMNS, $this->table, implode(' OR ', $conditions)),
            $parameters,
            $types,
        );
    }

    #[Override]
    public function subjects(ResourceReference $object, string $relation, int $limit, bool $setsOnly = false): array
    {
        return $this->tuples(
            \sprintf(
                'SELECT %s FROM %s WHERE object_kind = ? AND object_id = ? AND relation = ? AND (expires_at IS NULL OR expires_at > ?)%s ORDER BY subject_kind, subject_id, subject_relation',
                self::COLUMNS,
                $this->table,
                $setsOnly ? " AND subject_relation <> ''" : '',
            ),
            [$object->key, $object->id, $relation, $this->now()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
            $limit,
        );
    }

    #[Override]
    public function objects(string $kind, string $relation, Subject $subject, int $limit): array
    {
        return $this->tuples(
            \sprintf('SELECT %s FROM %s WHERE subject_kind = ? AND subject_id = ? AND subject_relation = ? AND object_kind = ? AND relation = ? AND (expires_at IS NULL OR expires_at > ?) ORDER BY object_id', self::COLUMNS, $this->table),
            [$subject->reference->key, $subject->reference->id, $subject->relation ?? '', $kind, $relation, $this->now()],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
            $limit,
        );
    }

    #[Override]
    public function forget(ResourceReference $object): int
    {
        return $this->connection->transactional(fn (Connection $connection): int => $this->count($connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE object_kind = ? AND object_id = ?', $this->table),
            [$object->key, $object->id],
        )) + $this->count($connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE subject_kind = ? AND subject_id = ?', $this->table),
            [$object->key, $object->id],
        )));
    }

    #[Override]
    public function purgeExpired(): int
    {
        return $this->count($this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE expires_at <= ?', $this->table),
            [$this->now()],
            [ParameterType::INTEGER],
        ));
    }

    /**
     * @return list<string>
     */
    private function key(RelationTuple $tuple): array
    {
        return [$tuple->object->key, $tuple->object->id, $tuple->relation, $tuple->subject->reference->key, $tuple->subject->reference->id, $tuple->subject->relation ?? ''];
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
     * The collation that compares identifiers byte by byte on the connection's platform.
     */
    /**
     * @return non-empty-string|null
     */
    private function collation(): ?string
    {
        $platform = $this->connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof AbstractMySQLPlatform => 'utf8mb4_bin',
            $platform instanceof PostgreSQLPlatform => 'C',
            default => null,
        };
    }

    /**
     * @param non-empty-string $name
     */
    private function textColumn(string $name, int $length): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);
        $collation = $this->collation();

        if (null !== $collation) {
            $column->setCollation($collation);
        }

        return $column->create();
    }

    private function tableDefinition(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->table)
            ->setColumns(
                $this->textColumn('object_kind', 64),
                $this->textColumn('object_id', 255),
                $this->textColumn('relation', 64),
                $this->textColumn('subject_kind', 64),
                $this->textColumn('subject_id', 255),
                $this->textColumn('subject_relation', 64),
                Column::editor()->setUnquotedName('expires_at')->setTypeName(Types::BIGINT)->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('object_kind', 'object_id', 'relation', 'subject_kind', 'subject_id', 'subject_relation')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName($this->table.'_subject')->setUnquotedColumnNames('subject_kind', 'subject_id', 'subject_relation', 'object_kind', 'relation')->create(),
                Index::editor()->setUnquotedName($this->table.'_expires_at')->setUnquotedColumnNames('expires_at')->create(),
            )
            ->create();
    }
}
