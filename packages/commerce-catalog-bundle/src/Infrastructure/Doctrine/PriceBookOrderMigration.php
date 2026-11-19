<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;

/**
 * Moves a table of price books ordered by a numeric `priority` to books ordered by their `after` lists, keeping the
 * order the books were tried in: each book is placed after every book of a higher priority, and books of the same
 * priority stay in the order of their codes.
 *
 * An application's Doctrine migration runs it once, from `postUp()`, with the migration's connection:
 *
 * ```php
 * public function postUp(Schema $schema): void
 * {
 *     PriceBookOrderMigration::run($this->connection);
 * }
 * ```
 *
 * It adds the column `after_books`, fills it, then drops the column `priority` and the indexes that held it, and adds
 * the index on `active` the mapping declares. A table already without `priority` is left as it is, so a second run
 * changes nothing.
 */
final class PriceBookOrderMigration
{
    public const string TABLE = 'phil_commerce_catalog_price_book';

    /**
     * Migrates the table; returns whether there was anything to migrate.
     */
    public static function run(Connection $connection): bool
    {
        $schema = $connection->createSchemaManager();
        $table = $schema->introspectTableByUnquotedName(self::TABLE);

        if (!self::column($table, 'priority') instanceof Column) {
            return false;
        }

        if (!self::column($table, 'after_books') instanceof Column) {
            $added = $table->edit()->addColumn(Column::editor()->setUnquotedName('after_books')->setTypeName('json')->setNotNull(false)->create())->create();
            $schema->alterTable($schema->createComparator()->compareTables($table, $added));
            $table = $schema->introspectTableByUnquotedName(self::TABLE);
        }

        foreach (self::afterLists($connection) as $code => $after) {
            $connection->update(self::TABLE, ['after_books' => json_encode($after, \JSON_THROW_ON_ERROR)], ['code' => $code]);
        }

        $editor = $table->edit()
            ->modifyColumnByUnquotedName('after_books', static function (ColumnEditor $column): void {
                $column->setNotNull(true);
            })
            ->dropColumnByUnquotedName('priority');
        $hasActiveIndex = false;

        foreach ($table->getIndexes() as $index) {
            $columns = self::indexedColumns($index);

            if (\in_array('priority', $columns, true)) {
                $editor->dropIndex($index->getObjectName());
            } elseif (['active'] === $columns) {
                $hasActiveIndex = true;
            }
        }

        if (!$hasActiveIndex) {
            $editor->addIndex(Index::editor()->setUnquotedName(PriceBook::ACTIVE_INDEX)->addUnquotedColumnName('active')->create());
        }

        $schema->alterTable($schema->createComparator()->compareTables($table, $editor->create()));

        return true;
    }

    /**
     * Each book's `after` list: the codes of the books of a higher priority, in the order they were tried — highest
     * priority first, then by code —, which is the order the sorter visits them in; past `PriceBook::MAX_AFTER`
     * books, the nearest ones.
     *
     * @return array<string, list<string>>
     */
    private static function afterLists(Connection $connection): array
    {
        $rows = [];

        foreach ($connection->fetchAllAssociative(\sprintf('SELECT code, priority FROM %s', self::TABLE)) as $row) {
            ['code' => $code, 'priority' => $priority] = $row;

            if (\is_string($code) && is_numeric($priority)) {
                $rows[] = ['code' => $code, 'priority' => (int) $priority];
            }
        }

        usort($rows, static fn (array $left, array $right): int => [$right['priority'], $left['code']] <=> [$left['priority'], $right['code']]);
        $lists = [];
        $higher = [];
        $level = [];
        $priority = null;

        foreach ($rows as $row) {
            if ($row['priority'] !== $priority) {
                array_push($higher, ...$level);
                $level = [];
                $priority = $row['priority'];
            }

            $lists[$row['code']] = \array_slice($higher, -PriceBook::MAX_AFTER);
            $level[] = $row['code'];
        }

        return $lists;
    }

    private static function column(Table $table, string $name): ?Column
    {
        foreach ($table->getColumns() as $column) {
            if (strtolower($column->getObjectName()->getIdentifier()->getValue()) === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return list<string> lowercase
     */
    private static function indexedColumns(Index $index): array
    {
        $columns = [];

        foreach ($index->getIndexedColumns() as $column) {
            $columns[] = strtolower($column->getColumnName()->getIdentifier()->getValue());
        }

        return $columns;
    }
}
