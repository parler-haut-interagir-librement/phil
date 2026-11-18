<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Override;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Infrastructure\Dbal\DbalTuplePartitions;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\FixedPartition;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\SinglePartition;
use Phil\ResourcePermissions\Subject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The partitions of a tuple table that `DbalTupleStore` created and filled, on a real connection: each partition that
 * holds tuples once, and never the default partition.
 */
abstract class TuplePartitionsTestCase extends TestCase
{
    private const string TABLE = 'phil_access_partitions_test';

    private ?Connection $connection = null;

    abstract protected function connect(): Connection;

    public function testEachPartitionThatHoldsTuplesOnce(): void
    {
        $connection = $this->connect();
        $this->connection = $connection;
        $connection->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);

        $clock = new MockClock('2026-11-17 12:00:00 UTC');

        foreach (new DbalTupleStore($connection, $clock, self::TABLE)->schemaUpdate() as $sql) {
            $connection->executeStatement($sql);
        }

        $partitions = new DbalTuplePartitions($connection, self::TABLE);
        self::assertSame([], $partitions->partitions());

        foreach (['globex' => ['a', 'b'], 'acme' => ['a'], 'retail-2' => ['c']] as $partition => $members) {
            $tuples = array_map(static fn (string $member): RelationTuple => new RelationTuple(ResourceReference::of('group', 'supporters'), 'member', Subject::of(ResourceReference::of('member', $member))), $members);
            new DbalTupleStore($connection, $clock, self::TABLE, new FixedPartition($partition))->update([], $tuples);
        }

        new DbalTupleStore($connection, $clock, self::TABLE, new SinglePartition())->update([], [new RelationTuple(ResourceReference::of('group', 'supporters'), 'member', Subject::of(ResourceReference::of('member', 'z')))]);

        $found = new DbalTuplePartitions($connection, self::TABLE)->partitions();
        sort($found, \SORT_STRING);

        self::assertSame(['acme', 'globex', 'retail-2'], $found, 'Each partition once, the default one left out.');
    }

    public function testATableNameFollowsTheRuleOfTheStore(): void
    {
        $connection = $this->connect();
        $this->connection = $connection;
        foreach (['tuples; DROP TABLE x', 'Tuples', '1tuples', '', str_repeat('t', 64)] as $table) {
            try {
                new DbalTuplePartitions($connection, $table);
                self::fail(\sprintf('"%s" was taken for a table name.', $table));
            } catch (InvalidAccess $invalidAccess) {
                self::assertSame(\sprintf('"%s" is not a table name: up to 63 lowercase letters, digits or "_", not starting with a digit.', $table), $invalidAccess->getMessage());
            }
        }

        self::assertInstanceOf(DbalTuplePartitions::class, new DbalTuplePartitions($connection, '_'.str_repeat('t', 61).'9'));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);
        $this->connection?->close();
        $this->connection = null;
    }
}
