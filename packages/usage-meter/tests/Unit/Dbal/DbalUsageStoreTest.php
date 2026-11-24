<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Override;
use Phil\UsageMeter\Counter;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Increment;
use Phil\UsageMeter\Tests\Unit\UsageStoreTestCase;
use Phil\UsageMeter\UsageStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The store on SQLite in memory, its tables created from its own schema.
 */
#[CoversClass(DbalUsageStore::class)]
final class DbalUsageStoreTest extends UsageStoreTestCase
{
    private ?Connection $connection = null;

    public function testTheTablesAreKeyedForTheIncrementAndIndexedForThePurge(): void
    {
        $this->store();
        $schema = $this->connection()->createSchemaManager()->introspectSchema();

        $counters = $schema->getTable('phil_usage_counter');
        self::assertSame(['subject', 'meter', 'starts_at'], $this->primaryKey($counters));
        self::assertSame(['subject' => ['string', 255], 'meter' => ['string', 64], 'starts_at' => ['bigint', null], 'used' => ['bigint', null], 'expires_at' => ['bigint', null]], $this->columns($counters->getColumns()));
        self::assertTrue($counters->hasIndex('phil_usage_counter_expires_at'));
        self::assertSame(['expires_at'], $counters->getIndex('phil_usage_counter_expires_at')->getColumns());

        $keys = $schema->getTable('phil_usage_key');
        self::assertSame(['subject', 'meter', 'idempotency_key'], $this->primaryKey($keys));
        self::assertSame(['subject' => ['string', 255], 'meter' => ['string', 64], 'idempotency_key' => ['string', 255], 'starts_at' => ['bigint', null], 'amount' => ['bigint', null], 'refunded' => ['bigint', null], 'expires_at' => ['bigint', null]], $this->columns($keys->getColumns()));
        self::assertSame(['expires_at'], $keys->getIndex('phil_usage_key_expires_at')->getColumns());
        self::assertContains($keys->getColumn('refunded')->getDefault(), [0, '0']);
    }

    public function testARefundTypesEachOfItsParameters(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => TypeRecordingConnection::class]);
        self::assertInstanceOf(TypeRecordingConnection::class, $connection);
        $store = new DbalUsageStore($connection);

        foreach ($store->configureSchema(new Schema())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 3, null, 'k');
        $connection->recorded = [];

        self::assertSame(3, $store->refund('a', 'm', 'k'));

        $string = ParameterType::STRING;
        $integer = ParameterType::INTEGER;
        self::assertSame([
            [$string, $string, $string],
            [$integer, $string, $string, $integer, $integer],
            [$integer, $string, $string, $string],
        ], $connection->recorded);
    }

    public function testTheTablesCanBeNamed(): void
    {
        $store = new DbalUsageStore($this->connection(), 'quota_counter', 'quota_key');
        $schema = $store->configureSchema(new Schema());

        self::assertTrue($schema->hasTable('quota_counter'));
        self::assertTrue($schema->hasTable('quota_key'));
        self::assertTrue($schema->getTable('quota_counter')->hasIndex('quota_counter_expires_at'));

        foreach ($schema->toSql($this->connection()->getDatabasePlatform()) as $statement) {
            $this->connection()->executeStatement($statement);
        }

        self::assertSame(Increment::Recorded, $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 1, null, 'k'));
        self::assertSame(1, $store->total('a', 'm', self::at(0), self::at(1)));
        self::assertSame(2, $store->purge(self::at(10)));
    }

    public function testATableAlreadyThereIsKept(): void
    {
        $store = new DbalUsageStore($this->connection());
        $schema = $store->configureSchema(new Schema());
        $again = $store->configureSchema($schema);

        self::assertCount(2, $again->getTables());
        self::assertSame(['subject', 'meter', 'starts_at', 'used', 'expires_at'], array_keys($this->columns($again->getTable('phil_usage_counter')->getColumns())));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTables(): iterable
    {
        yield 'empty' => [''];
        yield 'digit first' => ['1counter'];
        yield 'uppercase' => ['Counter'];
        yield 'quoted' => ['counter; DROP TABLE x'];
        yield 'sixty-four characters' => [str_repeat('a', 64)];
        yield 'trailing newline' => ["counter\n"];
    }

    #[DataProvider('invalidTables')]
    public function testATableNameIsPlain(string $table): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $table));

        new DbalUsageStore($this->connection(), 'counter', $table);
    }

    public function testATableNameMayStartWithAnUnderscoreAndRunSixtyThreeCharacters(): void
    {
        $store = new DbalUsageStore($this->connection(), '_'.str_repeat('a', 62), 'key_2');

        self::assertTrue($store->configureSchema(new Schema())->hasTable('_'.str_repeat('a', 62)));
    }

    public function testTheInsertDoesNothingOnAConflictOnEachPlatform(): void
    {
        $columns = ['subject', 'meter', 'starts_at', 'used'];

        self::assertSame('INSERT INTO t (subject, meter, starts_at, used) VALUES (?, ?, ?, ?) ON CONFLICT (subject, meter, starts_at) DO NOTHING', DbalUsageStore::insertUnlessPresent(new PostgreSQLPlatform(), 't', $columns));
        self::assertSame('INSERT IGNORE INTO t (subject, meter, starts_at, used) VALUES (?, ?, ?, ?)', DbalUsageStore::insertUnlessPresent(new MySQLPlatform(), 't', $columns));
        self::assertSame('INSERT IGNORE INTO t (subject) VALUES (?)', DbalUsageStore::insertUnlessPresent(new MariaDBPlatform(), 't', ['subject']));
        self::assertSame('INSERT OR IGNORE INTO t (subject, meter, starts_at, used) VALUES (?, ?, ?, ?)', DbalUsageStore::insertUnlessPresent(new SQLitePlatform(), 't', $columns));
    }

    public function testTheReadOfAKeyLocksItsRowExceptOnSqlite(): void
    {
        $select = 'SELECT starts_at, amount, refunded FROM t WHERE subject = ? AND meter = ? AND idempotency_key = ?';

        self::assertSame($select.' FOR UPDATE', DbalUsageStore::selectKeyForUpdate(new PostgreSQLPlatform(), 't'));
        self::assertSame($select.' FOR UPDATE', DbalUsageStore::selectKeyForUpdate(new MySQLPlatform(), 't'));
        self::assertSame($select, DbalUsageStore::selectKeyForUpdate(new SQLitePlatform(), 't'));
    }

    public function testADuplicateOrARefusalClosesItsTransaction(): void
    {
        $store = $this->store();
        $counter = new Counter('a', 'm', self::at(0), self::at(10));
        $store->add($counter, 1, null, 'k');

        self::assertSame(Increment::Duplicate, $store->add($counter, 1, null, 'k'));
        self::assertFalse($this->connection()->isTransactionActive());
        self::assertSame(Increment::Refused, $store->add($counter, 5, 5));
        self::assertFalse($this->connection()->isTransactionActive());
        self::assertSame(Increment::Recorded, $store->add($counter, 4, 5));
        self::assertFalse($this->connection()->isTransactionActive());
    }

    public function testARefundClosesItsTransactionWhateverItGivesBack(): void
    {
        $store = $this->store();
        $counter = new Counter('a', 'm', self::at(0), self::at(10));
        $store->add($counter, 3, null, 'k');

        self::assertSame(0, $store->refund('a', 'm', 'unknown'));
        self::assertFalse($this->connection()->isTransactionActive());
        self::assertSame(3, $store->refund('a', 'm', 'k'));
        self::assertFalse($this->connection()->isTransactionActive());
        self::assertSame(0, $store->refund('a', 'm', 'k'));
        self::assertFalse($this->connection()->isTransactionActive());
        self::assertEquals(3, $this->connection()->fetchOne('SELECT refunded FROM phil_usage_key'));
    }

    public function testARefundWithoutItsCounterGivesNothingBack(): void
    {
        $store = $this->store();
        $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 3, null, 'k');
        $this->connection()->executeStatement('DELETE FROM phil_usage_counter');

        self::assertSame(0, $store->refund('a', 'm', 'k'));
        self::assertEquals(0, $this->connection()->fetchOne('SELECT refunded FROM phil_usage_key'));
    }

    public function testAFailedRefundRollsBackAndIsLetThrough(): void
    {
        $store = new DbalUsageStore($this->connection());

        try {
            $store->refund('a', 'm', 'k');
            self::fail('The refund went through without its tables.');
        } catch (TableNotFoundException) {
            self::assertFalse($this->connection()->isTransactionActive());
        }
    }

    public function testARefundInsideAnOuterTransactionIsUndoneWithIt(): void
    {
        $store = $this->store();
        $connection = $this->connection();
        $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 3, null, 'k');
        $connection->beginTransaction();

        self::assertSame(3, $store->refund('a', 'm', 'k'));
        self::assertSame(0, $store->refund('a', 'm', 'unknown'));
        self::assertSame(1, $connection->getTransactionNestingLevel());

        $connection->rollBack();

        self::assertSame(3, $store->total('a', 'm', self::at(0), self::at(10)));
    }

    public function testOtherPlatformsAreRefused(): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains('DbalUsageStore runs on PostgreSQL, MySQL, MariaDB and SQLite; not on '.SQLServerPlatform::class.'.');

        DbalUsageStore::insertUnlessPresent(new SQLServerPlatform(), 't', ['a']);
    }

    public function testAFailedIncrementRollsBackAndIsLetThrough(): void
    {
        $store = new DbalUsageStore($this->connection());

        try {
            $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 1, null, 'k');
            self::fail('The increment went through without its tables.');
        } catch (TableNotFoundException) {
            self::assertFalse($this->connection()->isTransactionActive());
        }
    }

    public function testAFailureInsideAnOuterTransactionLeavesItToItsOwner(): void
    {
        $connection = $this->connection();
        $store = new DbalUsageStore($connection);
        $connection->beginTransaction();

        try {
            $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 1);
            self::fail('The increment went through without its tables.');
        } catch (TableNotFoundException) {
            self::assertTrue($connection->isTransactionActive());
            self::assertSame(1, $connection->getTransactionNestingLevel());
        }
    }

    public function testAnIncrementInsideAnOuterTransactionIsUndoneWithIt(): void
    {
        $store = $this->store();
        $connection = $this->connection();
        $connection->beginTransaction();

        self::assertSame(Increment::Recorded, $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 1, 5, 'k'));
        self::assertSame(Increment::Refused, $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 5, 5));
        self::assertSame(1, $connection->getTransactionNestingLevel());

        $connection->rollBack();

        self::assertSame(0, $store->total('a', 'm', self::at(0), self::at(10)));
    }

    #[Override]
    protected function store(): UsageStore
    {
        $store = new DbalUsageStore($this->connection());

        foreach ($store->configureSchema(new Schema())->toSql($this->connection()->getDatabasePlatform()) as $statement) {
            $this->connection()->executeStatement($statement);
        }

        return $store;
    }

    private function connection(): Connection
    {
        return $this->connection ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    /**
     * @return list<string>
     */
    private function primaryKey(Table $table): array
    {
        return array_map(static fn (UnqualifiedName $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []);
    }

    /**
     * @param list<Column> $columns
     *
     * @return array<string, array{string, int|null}>
     */
    private function columns(array $columns): array
    {
        $described = [];

        foreach ($columns as $column) {
            $described[$column->getName()] = [$column->getTypeName(), $column->getLength()];
        }

        return $described;
    }
}
