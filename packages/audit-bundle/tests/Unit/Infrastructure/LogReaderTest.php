<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Contract\AuditLogReader;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Order;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditLogReader;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use Phil\AuditBundle\Infrastructure\Scan\ScanningAuditLogReader;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The reader on the table and the reader that scans give the same pages for the same queries, and the table's reader
 * pages through a log on its key.
 */
#[CoversClass(DbalAuditLogReader::class)]
#[CoversClass(ScanningAuditLogReader::class)]
#[CoversClass(DbalAuditRowRepository::class)]
final class LogReaderTest extends TestCase
{
    private Connection $connection;

    private DbalAuditRowRepository $rows;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (AuditTable::configure($this->connection->createSchemaManager()->introspectSchema(), $this->connection->getDatabasePlatform())->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }

        $this->rows = new DbalAuditRowRepository($this->connection);
        $clock = new MockClock('2026-11-04 12:00 UTC');
        $trail = new AuditTrail($this->rows, new JsonSerializer(), $clock);
        $facts = [
            Facts::of('order.created', 'acct-7', Subject::of('order', '42'), 'shop-a'),
            Facts::of('order.created', 'acct-8', Subject::of('order', '43'), 'shop-b'),
            Facts::of('workflow.order.ship', 'acct-7', Subject::of('order', '42'), 'shop-a'),
            Facts::of('workflow.order_x.ship', 'acct-9', Subject::of('order', '44')),
            Facts::of('workflowxorder.ship', 'acct-9', Subject::of('invoice', '42')),
            Facts::of('member.joined', 'acct-8'),
            Facts::none(),
        ];

        foreach ($facts as $index => $fact) {
            $trail->record(0 === $index % 3 ? 'members' : 'orders', ['i' => $index], $fact);

            if (1 === $index % 2) {
                $clock->modify('+1 millisecond');
            }
        }

        $trail->recordDiscontinuity('orders', 'restored');
    }

    public function testBothReadersGiveTheSamePages(): void
    {
        $dbal = new DbalAuditLogReader($this->connection);
        $scan = new ScanningAuditLogReader($this->rows);
        $all = AuditQuery::all();
        $queries = [
            $all,
            $all->ordered(Order::OldestFirst),
            $all->byActor('acct-7'),
            $all->withAction('order.created'),
            $all->withAction('workflow.order.', true),
            $all->withAction('workflow.order_', true),
            $all->withAction('workflow.', true),
            $all->about(Subject::of('order', '42')),
            $all->aboutType('order'),
            $all->inSegment('members'),
            $all->inTenant('shop-a'),
            $all->between(new DateTimeImmutable('2026-11-04 12:00:00.001 UTC'), new DateTimeImmutable('2026-11-04 12:00:00.003 UTC')),
            $all->between(new DateTimeImmutable('2026-11-04 12:00:00.002 UTC'), null),
            $all->between(null, new DateTimeImmutable('2026-11-04 12:00:00.001 UTC')),
            $all->byActor('acct-9')->aboutType('order'),
            $all->byActor('nobody'),
        ];

        foreach ($queries as $index => $query) {
            foreach ([1, 2, 3, 50] as $limit) {
                foreach ([Order::NewestFirst, Order::OldestFirst] as $order) {
                    $paged = $query->ordered($order)->limitedTo($limit);
                    self::assertSame($this->walk($scan, $paged), $this->walk($dbal, $paged), \sprintf('query %d, limit %d, %s', $index, $limit, $order->value));
                }
            }
        }

        self::assertSame(['orders#2'], $this->walk($dbal, $all->withAction('workflow.order.', true)), 'An action prefix holds "_" literally.');
        self::assertSame(['members#2'], $this->walk($dbal, $all->withAction('workflow.order_', true)));
        $pageOf = static fn (AuditQuery $query): array => array_map(static fn (AuditRow $row): string => $row->segment.'#'.$row->sequence, $dbal->page($query->limitedTo(1))->rows);
        self::assertSame(['orders#2'], $pageOf($all->byActor('acct-7')), 'The table narrows by the actor itself: the first row read is one of acct-7.');
        self::assertSame(['members#2'], $pageOf($all->withAction('workflow.order_', true)), 'The table narrows by the prefix with "_" escaped, so the first row read matches.');
        self::assertSame(['orders#5', 'members#3', 'orders#4', 'orders#3', 'orders#2', 'members#2', 'orders#1', 'members#1'], $this->walk($dbal, $all), 'By instant, then segment, then sequence.');
    }

    public function testAPageHoldsTheLimitAndNoNextOneWhenTheLogEndsThere(): void
    {
        foreach ([new DbalAuditLogReader($this->connection), new ScanningAuditLogReader($this->rows)] as $reader) {
            $exact = $reader->page(AuditQuery::all()->limitedTo(8));
            self::assertCount(8, $exact->rows);
            self::assertTrue($exact->isLast(), 'Eight rows, a page of eight: no next page.');

            $short = $reader->page(AuditQuery::all()->limitedTo(7));
            self::assertCount(7, $short->rows);
            self::assertFalse($short->isLast());
            self::assertSame('members', $reader->page(AuditQuery::all()->limitedTo(7)->after($short->next))->rows[0]->segment);
        }
    }

    public function testOneRowMoreThanTheLimitIsRead(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAllAssociative')->with(self::stringContains(' LIMIT 4'))->willReturn([]);

        self::assertTrue(new DbalAuditLogReader($connection)->page(AuditQuery::all()->limitedTo(3))->isLast());
    }

    public function testARowIsReadByItsPlace(): void
    {
        $dbal = new DbalAuditLogReader($this->connection);
        $scan = new ScanningAuditLogReader($this->rows);

        foreach ([$dbal, $scan] as $reader) {
            $row = $reader->row('orders', 2);
            self::assertInstanceOf(AuditRow::class, $row);
            self::assertSame(['orders', 2, 'workflow.order.ship', 'acct-7', 'order', '42', 'shop-a'], [$row->segment, $row->sequence, $row->facts->action, $row->facts->actor, $row->facts->subject?->type, $row->facts->subject?->id, $row->facts->tenant]);
            self::assertTrue($row->isIntact());
            self::assertNull($reader->row('orders', 99));
            self::assertNull($reader->row('payments', 1));
        }

        self::assertEquals($scan->row('members', 3), $dbal->row('members', 3));
    }

    public function testAFactChangedInTheTableShowsInTheRow(): void
    {
        $this->connection->update(AuditTable::NAME, ['actor' => 'acct-1'], ['segment' => 'orders', 'sequence' => 2]);
        $this->connection->update(AuditTable::NAME, ['subject_id' => null], ['segment' => 'orders', 'sequence' => 1]);

        $dbal = new DbalAuditLogReader($this->connection);
        self::assertFalse($dbal->row('orders', 2)?->isIntact());
        self::assertNull($dbal->row('orders', 1)?->facts->subject, 'A subject without its identifier is no subject.');
        self::assertFalse($dbal->row('orders', 1)?->isIntact());
    }

    /**
     * Every page of the query, followed by its cursors.
     *
     * @return list<string>
     */
    private function walk(AuditLogReader $reader, AuditQuery $query): array
    {
        $places = [];
        $pages = 0;

        do {
            $page = $reader->page($query);
            self::assertLessThanOrEqual($query->limit, \count($page->rows));

            foreach ($page->rows as $row) {
                $places[] = $row->segment.'#'.$row->sequence;
            }

            $query = $query->after($page->next);
            self::assertLessThan(50, ++$pages);
        } while (!$page->isLast());

        return $places;
    }
}
