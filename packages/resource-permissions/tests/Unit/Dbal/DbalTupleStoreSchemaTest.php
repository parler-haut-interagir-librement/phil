<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit\Dbal;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Tests\Fixtures\SwitchableScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use UnexpectedValueException;

/**
 * What the DBAL store tells the database, which SQLite alone cannot show: the types of the parameters, the collation
 * of each platform and the width of each column.
 */
#[CoversClass(DbalTupleStore::class)]
final class DbalTupleStoreSchemaTest extends TestCase
{
    public function testATableNameEndingWithALineBreakIsRefused(): void
    {
        $this->expectException(InvalidRelation::class);

        new DbalTupleStore(self::createStub(Connection::class), new MockClock(), "acl\n");
    }

    public function testEveryTextColumnHasItsWidth(): void
    {
        $table = new DbalTupleStore($this->connection(new MySQLPlatform()), new MockClock())->configureSchema($this->schema())->getTable('phil_relation_tuple');

        $widths = [];

        foreach (['scope', 'object_kind', 'object_id', 'relation', 'subject_kind', 'subject_id', 'subject_relation'] as $column) {
            $widths[$column] = $table->getColumn($column)->getLength();
        }

        self::assertSame(['scope' => 128, 'object_kind' => 64, 'object_id' => 255, 'relation' => 64, 'subject_kind' => 64, 'subject_id' => 255, 'subject_relation' => 64], $widths);
        self::assertSame('', $table->getColumn('scope')->getDefault());
        self::assertTrue($table->getColumn('scope')->getNotnull());
    }

    public function testMySqlComparesByteByByteAndKeepsNamesInAscii(): void
    {
        $table = new DbalTupleStore($this->connection(new MySQLPlatform()), new MockClock())->configureSchema($this->schema())->getTable('phil_relation_tuple');
        $sets = [];

        foreach (['scope', 'object_kind', 'object_id', 'relation', 'subject_kind', 'subject_id', 'subject_relation'] as $column) {
            $sets[$column] = $table->getColumn($column)->getCharset().' '.$table->getColumn($column)->getCollation();
        }

        self::assertSame([
            'scope' => 'utf8mb4 utf8mb4_bin',
            'object_kind' => 'ascii ascii_bin',
            'object_id' => 'utf8mb4 utf8mb4_bin',
            'relation' => 'ascii ascii_bin',
            'subject_kind' => 'ascii ascii_bin',
            'subject_id' => 'utf8mb4 utf8mb4_bin',
            'subject_relation' => 'ascii ascii_bin',
        ], $sets);
    }

    public function testPostgreSqlComparesByteByByte(): void
    {
        $table = new DbalTupleStore($this->connection(new PostgreSQLPlatform()), new MockClock())->configureSchema($this->schema())->getTable('phil_relation_tuple');

        self::assertSame('C', $table->getColumn('subject_id')->getCollation());
        self::assertSame('C', $table->getColumn('relation')->getCollation());
        self::assertNull($table->getColumn('relation')->getCharset());
    }

    public function testSqliteKeepsItsOwnCollation(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $table = new DbalTupleStore($connection, new MockClock())->configureSchema($this->schema())->getTable('phil_relation_tuple');

        self::assertNull($table->getColumn('subject_id')->getCollation());
        self::assertNull($table->getColumn('relation')->getCharset());
    }

    public function testAWrittenTupleIsBoundWithItsTypes(): void
    {
        $statements = [];
        $store = new DbalTupleStore($this->recording($statements), new MockClock());
        $expiry = new MockClock('2026-11-13 10:00:00 UTC')->now();
        $object = ResourceReference::of('doc', '1');

        $store->update([], [
            new RelationTuple($object, 'viewer', Subject::fromString('member:ann')),
            new RelationTuple($object, 'viewer', Subject::fromString('member:bob'), $expiry),
        ]);

        $inserts = array_values(array_filter($statements, static fn (array $statement): bool => str_starts_with($statement[0], 'INSERT')));
        $strings = array_fill(0, 7, ParameterType::STRING);

        self::assertSame([...$strings, ParameterType::NULL], $inserts[0][2]);
        self::assertSame([...$strings, ParameterType::INTEGER], $inserts[1][2]);
        self::assertSame(['', 'doc', '1', 'viewer', 'member', 'bob', '', $expiry->getTimestamp()], $inserts[1][1]);
    }

    public function testEveryCallAsksTheScopeOnceBeforeAnyStatement(): void
    {
        $statements = [];
        $scope = new SwitchableScope();
        $scope->partition = 't1';

        $store = new DbalTupleStore($this->recording($statements), new MockClock(), scope: $scope);
        $tuple = new RelationTuple(ResourceReference::of('doc', '1'), 'viewer', Subject::fromString('member:ann'));

        $store->update([$tuple], [$tuple]);
        self::assertSame(1, $scope->asked);
        $store->forget(ResourceReference::of('doc', '1'));
        self::assertSame(2, $scope->asked);

        foreach ($statements as $i => [$sql, $parameters]) {
            self::assertSame('t1', $parameters[0]);

            if (2 !== $i) {
                self::assertStringContainsString(' WHERE scope = ? AND ', $sql);
            }
        }

        self::assertCount(5, $statements);
        self::assertStringStartsWith('INSERT INTO phil_relation_tuple (scope, object_kind,', $statements[2][0]);
        self::assertStringContainsString('WHERE scope = ? AND subject_kind = ? AND subject_id = ?', $statements[4][0]);
    }

    public function testThePurgeBindsTheTimeAsAnInteger(): void
    {
        $statements = [];

        new DbalTupleStore($this->recording($statements), new MockClock())->purgeExpired();

        self::assertSame([ParameterType::STRING, ParameterType::INTEGER], $statements[0][2]);
        self::assertSame('DELETE FROM phil_relation_tuple WHERE scope = ? AND expires_at <= ?', $statements[0][0]);
    }

    public function testAnExpiryThatIsNotANumberIsRefused(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $store = new DbalTupleStore($connection, new MockClock());
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        $connection->executeStatement("INSERT INTO phil_relation_tuple VALUES ('', 'doc', '1', 'viewer', 'member', 'ann', '', 'soon')");

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The expiry of a relation tuple is not a number.');

        $store->subjects(ResourceReference::of('doc', '1'), 'viewer', 10);
    }

    private function schema(): Schema
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
    }

    private function connection(AbstractPlatform $platform): Connection
    {
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return $connection;
    }

    /**
     * @param list<array{string, list<mixed>, list<mixed>}> $statements
     */
    private function recording(array &$statements): Connection
    {
        $connection = self::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (Closure $work): mixed => $work($connection));
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql, array $parameters = [], array $types = []) use (&$statements): int {
            $statements[] = [$sql, $parameters, $types];

            return 1;
        });

        return $connection;
    }
}
