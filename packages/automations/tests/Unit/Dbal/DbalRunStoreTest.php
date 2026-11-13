<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Dbal;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Override;
use Phil\Automations\Contract\RunStore;
use Phil\Automations\Dbal\DbalRunStore;
use Phil\Automations\Dbal\Sql;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Tests\Unit\Run\RunStoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use UnexpectedValueException;

#[CoversClass(DbalRunStore::class)]
#[CoversClass(Sql::class)]
final class DbalRunStoreTest extends RunStoreTestCase
{
    public function testTheSchemaIsAddedOnce(): void
    {
        $store = new DbalRunStore(SqliteConnection::memory(), 'runs');
        $schema = $store->configureSchema(SqliteConnection::memory()->createSchemaManager()->introspectSchema());

        self::assertTrue($schema->hasTable('runs'));
        self::assertSame($schema, $store->configureSchema($schema));
        self::assertCount(14, $schema->getTable('runs')->getColumns());
        self::assertTrue($schema->getTable('runs')->hasIndex('runs_due'));
        self::assertTrue($schema->getTable('runs')->hasIndex('runs_updated_at'));
    }

    public function testItRefusesABadTableName(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('"Runs" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.');

        new DbalRunStore(SqliteConnection::memory(), 'Runs');
    }

    public function testTheSqlHelpers(): void
    {
        self::assertSame('a_1', Sql::tableName('a_1'));
        self::assertSame(str_repeat('a', 63), Sql::tableName(str_repeat('a', 63)));
        self::assertSame(3, Sql::integer('3'));
        self::assertSame('x', Sql::string('x'));
        self::assertNull(Sql::nullableString(null));
        self::assertSame('x', Sql::nullableString('x'));

        $refused = 0;

        foreach ([static fn (): int => Sql::integer('x'), static fn (): string => Sql::string(1), static fn (): ?string => Sql::nullableString(1)] as $read) {
            try {
                $read();
            } catch (UnexpectedValueException) {
                ++$refused;
            }
        }

        self::assertSame(3, $refused);

        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('The DBAL stores of automations run on PostgreSQL, MySQL, MariaDB and SQLite; not on '.OraclePlatform::class.'.');
        Sql::insertUnlessPresent(new OraclePlatform(), 't', ['a'], ['a']);
    }

    public function testTheTableNameIsCheckedForLength(): void
    {
        $this->expectException(InvalidDefinition::class);
        Sql::tableName(str_repeat('a', 64));
    }

    #[Override]
    protected function store(): RunStore
    {
        $connection = SqliteConnection::memory();
        $store = new DbalRunStore($connection);
        SqliteConnection::migrate($connection, $store->configureSchema(...));

        return $store;
    }
}
