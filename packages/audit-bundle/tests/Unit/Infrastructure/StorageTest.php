<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\AuditBundle\Domain\Chain\Anchor;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Exception\ChainConflict;
use Phil\AuditBundle\Infrastructure\Anchor\JsonLinesAnchorStore;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditSchemaListener;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DbalAuditRowRepository::class)]
#[CoversClass(AuditTable::class)]
#[CoversClass(AuditSchemaListener::class)]
#[CoversClass(JsonLinesAnchorStore::class)]
final class StorageTest extends TestCase
{
    public function testTheTableKeepsTheChain(): void
    {
        $connection = $this->connection();
        $repository = new DbalAuditRowRepository($connection);
        $first = AuditRow::append(null, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 12:00:00.25 UTC'), 'v1', '{"a":1}');
        $second = AuditRow::append($first, 'orders', RowKind::Discontinuity, new DateTimeImmutable('2026-11-04 12:01 UTC'), 'v1', '{"b":2}');
        $member = AuditRow::append(null, 'members', RowKind::Event, new DateTimeImmutable('2026-11-04 12:02 UTC'), 'v1', '{}', Facts::of('member.joined', 'acct-7', Subject::of('member', 'm-1'), 'shop-a'));

        self::assertNull($repository->head('orders'));
        self::assertNull($repository->first('orders'));
        $repository->append($first);
        $repository->append($second);
        $repository->append($member);

        self::assertEquals($second, $repository->head('orders'));
        self::assertEquals($first, $repository->first('orders'));
        self::assertEquals($member, $repository->first('members'));
        self::assertEquals([$first, $second], iterator_to_array($repository->rows('orders'), false));
        self::assertSame(['members', 'orders'], $repository->segments());
        self::assertTrue($repository->head('orders')?->isIntact());
        self::assertTrue($repository->first('members')?->isIntact());
        self::assertEquals(Facts::none(), $repository->first('orders')?->facts);

        try {
            $repository->append(AuditRow::append($first, 'orders', RowKind::Event, new DateTimeImmutable(), 'v1', '{"c":3}'));
            self::fail('A place was taken twice.');
        } catch (ChainConflict $chainConflict) {
            self::assertSame('Row 2 of "orders" was appended by another process.', $chainConflict->getMessage());
        }
    }

    public function testTheTableIsAddedToItsConnectionsSchema(): void
    {
        $schema = AuditTable::configure($this->emptySchema(), new MySQLPlatform());
        $table = $schema->getTable('phil_audit_row');

        self::assertSame(['segment', 'sequence', 'kind', 'recorded_at', 'serialization_version', 'payload', 'previous_hash', 'hash', 'action', 'actor', 'subject_type', 'subject_id', 'tenant'], array_map(static fn (Column $column): string => $column->getName(), $table->getColumns()));
        self::assertSame([64, 128, 64, 128, 255], array_map(static fn (string $column): ?int => $table->getColumn($column)->getLength(), ['action', 'actor', 'subject_type', 'subject_id', 'tenant']));
        self::assertSame([false, false, false, false, false], array_map(static fn (string $column): bool => $table->getColumn($column)->getNotnull(), ['action', 'actor', 'subject_type', 'subject_id', 'tenant']));
        self::assertTrue($table->getColumn('hash')->getNotnull());
        self::assertSame(
            [
                'phil_audit_row_recorded' => ['recorded_at', 'segment', 'sequence'],
                'phil_audit_row_actor' => ['actor', 'recorded_at', 'segment', 'sequence'],
                'phil_audit_row_action' => ['action', 'recorded_at', 'segment', 'sequence'],
                'phil_audit_row_subject' => ['subject_type', 'subject_id', 'recorded_at', 'segment', 'sequence'],
                'phil_audit_row_tenant' => ['tenant', 'recorded_at', 'segment', 'sequence'],
            ],
            array_map(static fn (Index $index): array => array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns()), array_filter($table->getIndexes(), static fn (string $name): bool => str_starts_with($name, 'phil_audit_row_'), \ARRAY_FILTER_USE_KEY)),
        );
        self::assertSame([64, 16, 27, 32, 64, 64], [$table->getColumn('segment')->getLength(), $table->getColumn('kind')->getLength(), $table->getColumn('recorded_at')->getLength(), $table->getColumn('serialization_version')->getLength(), $table->getColumn('previous_hash')->getLength(), $table->getColumn('hash')->getLength()]);
        self::assertSame([true, true, true], [$table->getColumn('recorded_at')->getFixed(), $table->getColumn('previous_hash')->getFixed(), $table->getColumn('hash')->getFixed()]);
        self::assertSame([false, false, false], [$table->getColumn('segment')->getFixed(), $table->getColumn('kind')->getFixed(), $table->getColumn('serialization_version')->getFixed()]);
        self::assertSame(['segment', 'sequence'], $table->getPrimaryKey()?->getColumns());
        self::assertSame($schema, AuditTable::configure($schema, new MySQLPlatform()));

        $feed = $schema->getTable(AuditTable::FEED);
        self::assertSame([64, 64], [$feed->getColumn('feed')->getLength(), $feed->getColumn('segment')->getLength()]);
        self::assertSame('phil_audit_row', AuditTable::NAME);

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__]));
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $manager = new EntityManager($connection, $config);

        $event = new GenerateSchemaEventArgs($manager, $this->emptySchema());
        new AuditSchemaListener($connection)->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable('phil_audit_row'));

        $other = new GenerateSchemaEventArgs($manager, $this->emptySchema());
        new AuditSchemaListener($this->connection())->postGenerateSchema($other);
        self::assertFalse($other->getSchema()->hasTable('phil_audit_row'));
    }

    public function testTheTenantTheActorAndTheRecordCompareByteByByte(): void
    {
        $columns = ['actor', 'subject_id', 'tenant'];

        foreach ([[new MySQLPlatform(), 'utf8mb4_bin'], [new MariaDBPlatform(), 'utf8mb4_bin'], [new PostgreSQLPlatform(), 'C'], [new SQLitePlatform(), null]] as [$platform, $collation]) {
            $table = AuditTable::configure($this->emptySchema(), $platform)->getTable(AuditTable::NAME);

            self::assertSame([$collation, $collation, $collation], array_map(static fn (string $column): ?string => $table->getColumn($column)->getCollation(), $columns), $platform::class);
            self::assertSame([null, null, null, null], array_map(static fn (string $column): ?string => $table->getColumn($column)->getCollation(), ['segment', 'action', 'subject_type', 'payload']), 'The other columns keep the default one.');
            self::assertSame($collation, AuditTable::binaryCollation($platform));
        }

        self::assertStringContainsString('tenant VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_bin`', implode(';', AuditTable::configure($this->emptySchema(), new MySQLPlatform())->toSql(new MySQLPlatform())));
    }

    public function testAnchorsAreAppendedAndReadBack(): void
    {
        $file = sys_get_temp_dir().'/phil-audit-'.bin2hex(random_bytes(4)).'.jsonl';
        $store = new JsonLinesAnchorStore($file);
        $at = new DateTimeImmutable('2026-11-04T23:59:00+00:00');

        self::assertSame([], $store->anchors('orders'));
        $store->write('orders', $at, str_repeat('a', 64), str_repeat('b', 64));
        $store->write('members', $at, str_repeat('c', 64), str_repeat('d', 64));
        $store->write('orders', $at->modify('+1 day'), str_repeat('a', 64), str_repeat('e', 64));
        file_put_contents($file, "not json\n".'{"segment":"orders","period_end":1}'."\n\n".'{"segment":"orders","period_end":"2026-11-06T00:00:00+00:00","first_hash":"a","head_hash":5}'."\n".'{"segment":"orders","first_hash":"a","head_hash":"b"}'."\n".'{"segment":"orders","period_end":"2026-11-06T00:00:00+00:00","head_hash":"b"}'."\n", \FILE_APPEND);

        self::assertEquals([new Anchor('orders', $at, str_repeat('a', 64), str_repeat('b', 64)), new Anchor('orders', $at->modify('+1 day'), str_repeat('a', 64), str_repeat('e', 64))], $store->anchors('orders'));
        self::assertCount(1, $store->anchors('members'));
        self::assertStringStartsWith('{"segment":"orders","period_end":"2026-11-04T23:59:00+00:00","first_hash":"'.str_repeat('a', 64).'","head_hash":"'.str_repeat('b', 64)."\"}\n", (string) file_get_contents($file));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The anchor of "orders" could not be written to /nonexistent/directory/anchors.jsonl.');
        new JsonLinesAnchorStore('/nonexistent/directory/anchors.jsonl')->write('orders', $at, 'a', 'b');
    }

    private function emptySchema(): Schema
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
    }

    private function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (AuditTable::configure($this->emptySchema(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection;
    }
}
