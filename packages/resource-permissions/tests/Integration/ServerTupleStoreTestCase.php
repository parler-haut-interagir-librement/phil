<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Tests\Unit\TupleStoreTestCase;

/**
 * Every promise of a store, on a real server read from the environment: the byte-by-byte collation of its dialect,
 * the primary key's length within the server's limit, and a listing over a long list.
 */
abstract class ServerTupleStoreTestCase extends TupleStoreTestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testTheLongestIdentifiersAreKept(): void
    {
        $store = $this->store();
        $kind = 'k'.str_repeat('a', 63);
        $id = str_repeat('é', 127).'a';
        $tuple = new RelationTuple(ResourceReference::of($kind, $id), 'r'.str_repeat('b', 63), Subject::set(ResourceReference::of($kind, $id), 'r'.str_repeat('c', 63)));
        $store->update([], [$tuple]);

        self::assertSame([$tuple->key()], self::keys($store->subjects($tuple->object, $tuple->relation, 10)));
    }

    public function testALongListIsCheckedAndListedWithinTheBudget(): void
    {
        $store = $this->store();
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('group', ['member' => Rewrite::direct('member')]),
            TypeDefinition::of('doc', ['viewer' => Rewrite::direct('member', 'group#member')]),
        );
        $tuples = [];

        foreach (range(1, 2000) as $i) {
            $tuples[] = new RelationTuple(ResourceReference::of('doc', \sprintf('%05d', $i)), 'viewer', Subject::fromString('member:m'.($i % 50)));
        }

        $tuples[] = new RelationTuple(ResourceReference::of('group', 'g'), 'member', Subject::fromString('member:ann'));

        foreach (range(1, 300) as $i) {
            $tuples[] = new RelationTuple(ResourceReference::of('doc', \sprintf('g%04d', $i)), 'viewer', Subject::fromString('group:g#member'));
        }

        $store->update([], $tuples);
        $checker = new RelationChecker($model, $store);

        self::assertTrue($checker->check(ResourceReference::of('member', 'm7'), 'viewer', ResourceReference::of('doc', '00007')));
        self::assertFalse($checker->check(ResourceReference::of('member', 'm7'), 'viewer', ResourceReference::of('doc', '00008')));
        $list = $checker->objects(ResourceReference::of('member', 'ann'), 'viewer', 'doc', 100);
        self::assertCount(100, $list->objects);
        self::assertSame('g0001', $list->objects[0]->id);
        self::assertFalse($list->complete);
    }

    #[Override]
    protected function store(): TupleStore
    {
        $connection = $this->connection ??= $this->connect();
        $store = new DbalTupleStore($connection, $this->clock);
        $connection->executeStatement('DROP TABLE IF EXISTS '.DbalTupleStore::TABLE);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    private function connect(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
