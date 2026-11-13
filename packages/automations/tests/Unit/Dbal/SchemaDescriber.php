<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Dbal;

use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Table;

/**
 * A table of a schema as plain values: its columns, its primary key, its indexes.
 */
final class SchemaDescriber
{
    /**
     * @return array<string, array{string, int|null, bool, bool}> the type, the length, whether it is fixed and whether it is not null, by column
     */
    public static function columns(Table $table): array
    {
        $columns = [];

        foreach ($table->getColumns() as $column) {
            $columns[$column->getObjectName()->toString()] = [$column->getTypeName(), $column->getLength(), $column->getFixed(), $column->getNotnull()];
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    public static function primaryKey(Table $table): array
    {
        return array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []);
    }

    /**
     * @return list<string>
     */
    public static function index(Table $table, string $name): array
    {
        return array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $table->getIndex($name)->getIndexedColumns());
    }
}
