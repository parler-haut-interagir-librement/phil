<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\RecordingConnection;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\StoreDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoreTables::class)]
final class StoreTablesTest extends TestCase
{
    public function testTheTableOfThePlacements(): void
    {
        $table = StoreTables::configure(Schema::editor()->create(), suspensions: false)->getTable(StoreTables::PLACEMENTS);

        self::assertSame(['id'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(64, $table->getColumn('id')->getLength());
        self::assertTrue($table->getColumn('id')->getFixed());
        self::assertSame(255, $table->getColumn('tenant')->getLength());
        self::assertTrue($table->getColumn('tenant')->getNotnull());
        self::assertSame(63, $table->getColumn('database_name')->getLength());
        self::assertFalse($table->getColumn('database_name')->getNotnull());
        self::assertSame(Types::DATETIME_IMMUTABLE, $table->getColumn('placed_at')->getTypeName());
        self::assertSame('CURRENT_TIMESTAMP', $table->getColumn('placed_at')->getDefault());
        self::assertTrue($table->getColumn('placed_at')->getNotnull());
    }

    public function testTheTableOfTheSuspensions(): void
    {
        $schema = StoreTables::configure(Schema::editor()->create(), placements: false);
        $table = $schema->getTable(StoreTables::SUSPENSIONS);

        self::assertFalse($schema->hasTable(StoreTables::PLACEMENTS));
        self::assertSame(['id'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(64, $table->getColumn('id')->getLength());
        self::assertTrue($table->getColumn('id')->getFixed());
        self::assertSame(8, $table->getColumn('kind')->getLength());
        self::assertSame(255, $table->getColumn('subject')->getLength());
        self::assertFalse($table->getColumn('subject')->getNotnull());
        self::assertSame('CURRENT_TIMESTAMP', $table->getColumn('suspended_at')->getDefault());
    }

    public function testOnlyTheFixedColumnsAreFixed(): void
    {
        $schema = StoreTables::configure(Schema::editor()->create());

        self::assertFalse($schema->getTable(StoreTables::PLACEMENTS)->getColumn('tenant')->getFixed());
        self::assertFalse($schema->getTable(StoreTables::PLACEMENTS)->getColumn('database_name')->getFixed());
        self::assertFalse($schema->getTable(StoreTables::SUSPENSIONS)->getColumn('kind')->getFixed());
        self::assertFalse($schema->getTable(StoreTables::SUSPENSIONS)->getColumn('subject')->getFixed());
    }

    public function testWithoutTheSuspensionsTheirTableIsNotAdded(): void
    {
        $schema = StoreTables::configure(Schema::editor()->create(), suspensions: false);

        self::assertTrue($schema->hasTable(StoreTables::PLACEMENTS));
        self::assertFalse($schema->hasTable(StoreTables::SUSPENSIONS));
    }

    public function testBothTablesByDefaultAndATableThereIsKept(): void
    {
        $schema = Schema::editor()->addTable(Table::editor()
            ->setUnquotedName(StoreTables::PLACEMENTS)
            ->setColumns(Column::editor()->setUnquotedName('own')->setTypeName(Types::INTEGER)->create())
            ->create())->create();

        $schema = StoreTables::configure($schema);

        self::assertSame(['own'], array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $schema->getTable(StoreTables::PLACEMENTS)->getColumns()));
        self::assertTrue($schema->hasTable(StoreTables::SUSPENSIONS));
        self::assertSame(['phil_tenancy_placement', 'phil_tenancy_suspension'], array_map(static fn (Table $table): string => $table->getObjectName()->toString(), StoreTables::configure(Schema::editor()->create())->getTables()));
    }

    public function testAnUpsertInsertsThenReplacesTheColumnsItNames(): void
    {
        $connection = StoreDatabase::connection();
        $connection->executeStatement("INSERT INTO phil_tenancy_placement (id, tenant, database_name, placed_at) VALUES ('b', 'beta', NULL, '2000-01-01 00:00:00')");

        StoreTables::upsert($connection, StoreTables::PLACEMENTS, ['id' => 'a', 'tenant' => 'alpha', 'database_name' => 'one'], ['database_name'], 'placed_at');
        StoreTables::upsert($connection, StoreTables::PLACEMENTS, ['id' => 'b', 'tenant' => 'other', 'database_name' => 'two'], ['database_name'], 'placed_at');

        $rows = $connection->fetchAllAssociative('SELECT id, tenant, database_name, placed_at FROM phil_tenancy_placement ORDER BY id');

        self::assertSame(['a', 'alpha', 'one'], [$rows[0]['id'], $rows[0]['tenant'], $rows[0]['database_name']]);
        // The tenant is not among the columns to replace.
        self::assertSame(['b', 'beta', 'two'], [$rows[1]['id'], $rows[1]['tenant'], $rows[1]['database_name']]);
        self::assertNotSame('2000-01-01 00:00:00', $rows[1]['placed_at']);
    }

    public function testAnUpsertWithoutColumnsLeavesTheRowThere(): void
    {
        $connection = StoreDatabase::connection();
        $connection->executeStatement("INSERT INTO phil_tenancy_suspension (id, kind, subject, suspended_at) VALUES ('a', 'tenant', 'alpha', '2000-01-01 00:00:00')");

        StoreTables::upsert($connection, StoreTables::SUSPENSIONS, ['id' => 'a', 'kind' => 'tenant', 'subject' => 'other']);

        self::assertSame([['id' => 'a', 'kind' => 'tenant', 'subject' => 'alpha', 'suspended_at' => '2000-01-01 00:00:00']], $connection->fetchAllAssociative('SELECT id, kind, subject, suspended_at FROM phil_tenancy_suspension'));
    }

    public function testAnUpsertTouchesWithoutReplacing(): void
    {
        $connection = StoreDatabase::connection();
        $connection->executeStatement("INSERT INTO phil_tenancy_placement (id, tenant, database_name, placed_at) VALUES ('a', 'alpha', 'one', '2000-01-01 00:00:00')");

        StoreTables::upsert($connection, StoreTables::PLACEMENTS, ['id' => 'a', 'tenant' => 'alpha', 'database_name' => 'two'], touched: 'placed_at');

        $row = $connection->fetchAssociative('SELECT database_name, placed_at FROM phil_tenancy_placement');
        self::assertIsArray($row);
        self::assertSame('one', $row['database_name']);
        self::assertNotSame('2000-01-01 00:00:00', $row['placed_at']);
    }

    public function testTheStatementsOfMySql(): void
    {
        $connection = RecordingConnection::mysql();

        StoreTables::upsert($connection, 'things', ['id' => 'a', 'name' => 'alpha', 'size' => null], ['name', 'size'], 'seen_at');
        StoreTables::upsert($connection, 'things', ['id' => 'a', 'name' => 'alpha'], ['name']);
        StoreTables::upsert($connection, 'things', ['id' => 'a'], touched: 'seen_at');
        StoreTables::upsert($connection, 'things', ['id' => 'a', 'name' => 'alpha']);

        self::assertSame([
            ['INSERT INTO things (id, name, size) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, size = ?, seen_at = CURRENT_TIMESTAMP', ['a', 'alpha', null, 'alpha', null]],
            ['INSERT INTO things (id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?', ['a', 'alpha', 'alpha']],
            ['INSERT INTO things (id) VALUES (?) ON DUPLICATE KEY UPDATE seen_at = CURRENT_TIMESTAMP', ['a']],
            ['INSERT INTO things (id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id', ['a', 'alpha']],
        ], $connection->statements);
    }
}
