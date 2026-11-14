<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Integration\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\DashboardFeed;
use Phil\AuditBundle\Application\TrailVerification;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\SiteSegment;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Exception\ChainConflict;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Order;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditLogReader;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalFeedCheckpoints;
use Phil\AuditBundle\Infrastructure\Scan\ScanningAuditLogReader;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use Phil\AuditBundle\Tests\Unit\Fixture\RecordingExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The tables of the trail and of the feeds on a real server: what SQLite lets pass and a server may not — the text
 * order of the instants, the facts in UTF-8, the unique key of a place, a sequence read back as a string.
 */
abstract class SqlStorageTestCase extends TestCase
{
    private ?Connection $connection = null;

    /**
     * The URL of the server, or `null` to skip the test.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function setUp(): void
    {
        $url = $this->url();

        if (null === $url || '' === $url) {
            self::markTestSkipped('Start the servers with `phil-dev services up` and run `phil-dev services test` to run this test.');
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $manager = $connection->createSchemaManager();

        foreach ([AuditTable::NAME, AuditTable::FEED] as $table) {
            if ($manager->tablesExist([$table])) {
                $connection->executeStatement('DROP TABLE '.$table);
            }
        }

        // The server's database holds the tables of other suites: only the difference is applied.
        $current = $manager->introspectSchema();

        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($manager->createComparator()->compareSchemas($current, AuditTable::configure($current, $connection->getDatabasePlatform()))) as $sql) {
            $connection->executeStatement($sql);
        }

        $this->connection = $connection;
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testTheTrailIsKeptReadAndVerified(): void
    {
        $connection = $this->connection();
        $rows = new DbalAuditRowRepository($connection);
        $clock = new MockClock('2026-11-12 09:00:00.000001 UTC');
        $trail = new AuditTrail($rows, new JsonSerializer(), $clock, siteSegments: true);

        foreach (range(1, 7) as $order) {
            $trail->record('orders', ['order' => $order], Facts::of(0 === $order % 2 ? 'order.paid' : 'order.created', 'acct-'.($order % 3), Subject::of('order', (string) $order), 'Boutique é/1'));
            $clock->modify('+999 milliseconds');
        }

        $trail->record('orders', ['order' => 8], Facts::of('order.created', 'acct-1'));
        $site = SiteSegment::of('orders', 'Boutique é/1');

        self::assertSame([$site, 'orders'], [$rows->head($site)?->segment, $rows->head('orders')?->segment]);
        $first = $rows->first($site);
        self::assertInstanceOf(AuditRow::class, $first);
        self::assertSame('Boutique é/1', $first->facts->tenant);
        self::assertSame([3, 4, 5], array_map(static fn (AuditRow $row): int => $row->sequence, $rows->rowsAfter($site, 2, 3)));
        self::assertSame([], new TrailVerification($rows)->verify()[$site]);

        $table = new DbalAuditLogReader($connection);
        $scan = new ScanningAuditLogReader($rows);

        foreach ([AuditQuery::all(), AuditQuery::all()->inTenant('Boutique é/1')->ordered(Order::OldestFirst), AuditQuery::all()->byActor('acct-1'), AuditQuery::all()->withAction('order.', true)] as $query) {
            $query = $query->limitedTo(3);

            do {
                $expected = $scan->page($query);
                $page = $table->page($query);
                self::assertSame(array_map(static fn (AuditRow $row): string => $row->hash, $expected->rows), array_map(static fn (AuditRow $row): string => $row->hash, $page->rows));
                $query = $query->after($page->next);
            } while (!$page->isLast());
        }

        $this->expectException(ChainConflict::class);
        $rows->append($first);
    }

    public function testOneTenantNeverReadsAnothersTrail(): void
    {
        $connection = $this->connection();
        $rows = new DbalAuditRowRepository($connection);
        $clock = new MockClock('2026-11-13 09:00:00 UTC');
        $trail = new AuditTrail($rows, new JsonSerializer(), $clock);

        foreach ([['acme', 'acct-7', 'a1'], ['ACME', 'ACCT-7', 'A1'], ['acmé', 'acct-7', 'a1'], ['Acme ', 'acct-7', 'a1'], ['zeta', 'acct-9', 'z1']] as [$tenant, $actor, $id]) {
            $trail->record('orders', [], Facts::of('order.created', $actor, Subject::of('order', $id), rtrim($tenant)));
            $clock->modify('+1 second');
        }

        // The server itself compares the three columns byte by byte.
        self::assertEquals(1, $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE tenant = ?', AuditTable::NAME), ['acme']));
        self::assertEquals(3, $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE actor = ?', AuditTable::NAME), ['acct-7']));
        self::assertEquals(1, $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE subject_id = ?', AuditTable::NAME), ['A1']));

        $reader = new DbalAuditLogReader($connection);
        $tenants = static fn (AuditQuery $query): array => array_map(static fn (AuditRow $row): ?string => $row->facts->tenant, $reader->page($query)->rows);

        self::assertSame(['acme'], $tenants(AuditQuery::all()->inTenant('acme')));
        self::assertSame(['ACME'], $tenants(AuditQuery::all()->inTenant('ACME')));
        self::assertSame(['acmé'], $tenants(AuditQuery::all()->inTenant('acmé')));
        self::assertSame(['Acme'], $tenants(AuditQuery::all()->inTenant('Acme')));
        self::assertSame(['ACME'], $tenants(AuditQuery::all()->about(Subject::of('order', 'A1'))));
    }

    public function testTheFeedKeepsItsPlaces(): void
    {
        $connection = $this->connection();
        $rows = new DbalAuditRowRepository($connection);
        $trail = new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-12 09:00 UTC'));

        foreach (range(1, 5) as $order) {
            $trail->record('orders', ['order' => $order]);
        }

        $checkpoints = new DbalFeedCheckpoints($connection);
        $exporter = new RecordingExporter();
        $feed = new DashboardFeed($rows, $exporter, $checkpoints, batch: 2);

        self::assertSame(['orders' => 5], $feed->feed());
        self::assertSame(5, $checkpoints->position('dashboards', 'orders'));
        $checkpoints->advance('dashboards', 'orders', 3);
        self::assertSame(5, $checkpoints->position('dashboards', 'orders'), 'Never back.');
        self::assertSame([], $feed->feed());
        self::assertSame(0, $feed->lag('orders'));
        self::assertCount(3, $exporter->batches);
    }

    private function connection(): Connection
    {
        return $this->connection ?? self::fail('No connection.');
    }
}
