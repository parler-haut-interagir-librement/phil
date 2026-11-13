<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Tests\Unit\TupleStoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on SQLite in memory; the same promises on MySQL and PostgreSQL are the integration suite's.
 */
#[CoversClass(DbalTupleStore::class)]
final class DbalTupleStoreTest extends TupleStoreTestCase
{
    private ?Connection $connection = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testTheSchemaHasTheTableItsKeyAndItsIndexes(): void
    {
        $store = new DbalTupleStore($this->connection(), $this->clock, 'acl');
        $schema = $store->configureSchema($this->connection()->createSchemaManager()->introspectSchema());
        $table = $schema->getTable('acl');

        $key = $table->getPrimaryKeyConstraint();
        self::assertNotNull($key);
        self::assertSame(['object_kind', 'object_id', 'relation', 'subject_kind', 'subject_id', 'subject_relation'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $key->getColumnNames()));
        self::assertTrue($table->hasIndex('acl_subject'));
        self::assertTrue($table->hasIndex('acl_expires_at'));
        self::assertFalse($table->getColumn('expires_at')->getNotnull());
        self::assertTrue($table->getColumn('subject_id')->getNotnull());
        self::assertSame(255, $table->getColumn('object_id')->getLength());
        self::assertSame(64, $table->getColumn('relation')->getLength());
        self::assertSame($schema, $store->configureSchema($schema));
    }

    public function testATableNameAgainstTheRulesIsRefused(): void
    {
        $this->expectException(InvalidRelation::class);

        new DbalTupleStore($this->connection(), $this->clock, 'acl; DROP TABLE x');
    }

    public function testATableNameMayHoldDigitsAndUnderscores(): void
    {
        $store = new DbalTupleStore($this->connection(), $this->clock, '_acl_2');

        self::assertTrue($store->configureSchema($this->connection()->createSchemaManager()->introspectSchema())->hasTable('_acl_2'));
    }

    public function testAMatchOfTooManySubjectsIsRefused(): void
    {
        $subjects = array_map(static fn (int $i): Subject => Subject::of(ResourceReference::of('member', (string) $i)), range(1, DbalTupleStore::MAX_MATCHING + 1));

        $this->expectException(InvalidRelation::class);

        $this->store()->matching(ResourceReference::of('doc', '1'), ['viewer'], $subjects);
    }

    public function testAMatchOfTooManyRelationsIsRefused(): void
    {
        $relations = array_map(static fn (int $i): string => 'r'.$i, range(1, DbalTupleStore::MAX_MATCHING + 1));

        $this->expectException(InvalidRelation::class);

        $this->store()->matching(ResourceReference::of('doc', '1'), $relations, [Subject::fromString('member:a')]);
    }

    public function testAMatchOfTheMostSubjectsIsRead(): void
    {
        $subjects = array_map(static fn (int $i): Subject => Subject::of(ResourceReference::of('member', (string) $i)), range(1, DbalTupleStore::MAX_MATCHING));
        $relations = array_map(static fn (int $i): string => 'r'.$i, range(1, DbalTupleStore::MAX_MATCHING));
        $store = $this->store();
        $store->update([], [new RelationTuple(ResourceReference::of('doc', '1'), 'r256', $subjects[255])]);

        self::assertCount(1, $store->matching(ResourceReference::of('doc', '1'), $relations, $subjects));
    }

    public function testAFailedUpdateLeavesNothingBehind(): void
    {
        $connection = $this->connection();
        $store = $this->store();
        $store->update([], [$this->tuple('doc:1', 'viewer', 'member:ann')]);

        $connection->executeStatement("CREATE TRIGGER refuse BEFORE INSERT ON phil_relation_tuple WHEN NEW.subject_id = 'bad' BEGIN SELECT RAISE(ABORT, 'refused'); END");

        try {
            $store->update([$this->tuple('doc:1', 'viewer', 'member:ann')], [$this->tuple('doc:1', 'viewer', 'member:bad')]);
            self::fail('The update should have failed.');
        } catch (DriverException) {
        }

        self::assertSame(['doc:1#viewer@member:ann'], self::keys($store->subjects(self::ref('doc:1'), 'viewer', 10)));
    }

    #[Override]
    protected function store(): TupleStore
    {
        $connection = $this->connection();
        $store = new DbalTupleStore($connection, $this->clock);
        $connection->executeStatement('DROP TABLE IF EXISTS '.DbalTupleStore::TABLE);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    private function connection(): Connection
    {
        return $this->connection ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
