<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\Parsers;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Override;
use Phil\PrivacyBundle\Application\Contract\AnonymizationTarget;
use Phil\PrivacyBundle\Application\Exception\AnonymizationRefused;

/**
 * The rows of a Doctrine DBAL connection, read in pages ordered by primary key and written one by one by key.
 *
 * Pages keep memory flat on a large table; ordering by key keeps each row in exactly one page, since the run never
 * changes a key. Names are read as Doctrine writes them, unquoted — PostgreSQL folds `firstName` to `firstname` —,
 * and a schema may prefix a table, as in `billing.invoice`.
 */
final readonly class DbalAnonymizationTarget implements AnonymizationTarget
{
    public function __construct(
        private Connection $connection,
        private int $batchSize,
    ) {
    }

    #[Override]
    public function keyOf(string $table): array
    {
        $key = $this->connection->createSchemaManager()->introspectTablePrimaryKeyConstraint($this->name($table));
        $columns = array_map(static fn (UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $key?->getColumnNames() ?? []);

        return [] === $columns ? throw AnonymizationRefused::withoutKey($table) : $columns;
    }

    #[Override]
    public function rows(string $table, array $key, array $columns): iterable
    {
        $select = \sprintf(
            'SELECT %s FROM %s ORDER BY %s',
            implode(', ', array_map(fn (string $column): string => $this->column($column).' AS '.$this->connection->quoteSingleIdentifier($column), [...$key, ...$columns])),
            $this->table($table),
            implode(', ', array_map($this->column(...), $key)),
        );

        for ($offset = 0;; $offset += $this->batchSize) {
            $rows = $this->connection->fetchAllAssociative($this->connection->getDatabasePlatform()->modifyLimitQuery($select, $this->batchSize, $offset));

            yield from $rows;

            if (\count($rows) < $this->batchSize) {
                return;
            }
        }
    }

    #[Override]
    public function update(string $table, array $key, array $values): void
    {
        $this->connection->update($this->table($table), $this->columns($values), $this->columns($key));
    }

    #[Override]
    public function transactional(callable $work): mixed
    {
        return $this->connection->transactional(static fn (): mixed => $work());
    }

    /**
     * @param non-empty-string $table
     */
    private function name(string $table): OptionallyQualifiedName
    {
        return Parsers::parseOptionallyQualifiedName($table);
    }

    /**
     * @param non-empty-string $table
     */
    private function table(string $table): string
    {
        return $this->name($table)->toSQL($this->connection->getDatabasePlatform());
    }

    /**
     * @param non-empty-string $column
     */
    private function column(string $column): string
    {
        return Identifier::unquoted($column)->toSQL($this->connection->getDatabasePlatform());
    }

    /**
     * @template T
     *
     * @param array<non-empty-string, T> $values
     *
     * @return array<string, T>
     */
    private function columns(array $values): array
    {
        return array_combine(array_map($this->column(...), array_keys($values)), $values);
    }
}
