<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\AuditBundle\Application\AuditLog;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\ChainStatus;
use Phil\AuditBundle\Application\Contract\AuditNotifier;
use Phil\AuditBundle\Application\TrailVerification;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\ChainState;
use Phil\AuditBundle\Domain\Read\Order;
use Phil\AuditBundle\Domain\Read\SegmentStatus;
use Phil\AuditBundle\Infrastructure\Scan\ScanningAuditLogReader;
use Phil\AuditBundle\Tests\Unit\Fixture\FixedTenant;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryAuditRows;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(AuditLog::class)]
#[CoversClass(ChainStatus::class)]
#[CoversClass(AuditTrail::class)]
#[CoversClass(ScanningAuditLogReader::class)]
final class ReadSideTest extends TestCase
{
    private InMemoryAuditRows $rows;

    private MockClock $clock;

    private FixedTenant $tenant;

    #[Override]
    protected function setUp(): void
    {
        $this->rows = new InMemoryAuditRows();
        $this->clock = new MockClock('2026-11-04 12:00 UTC');
        $this->tenant = new FixedTenant();
    }

    public function testTheTrailStampsTheTenantAndTellsTheNotifier(): void
    {
        $notifier = new class implements AuditNotifier {
            /** @var list<string> */
            public array $told = [];

            #[Override]
            public function recorded(AuditRow $row): void
            {
                $this->told[] = $row->segment.'#'.$row->sequence;
            }
        };
        $trail = new AuditTrail($this->rows, new JsonSerializer(), $this->clock, $this->tenant, $notifier);

        $outside = $trail->record('orders', ['a' => 1], Facts::of('order.paid', 'acct-7'));
        $this->tenant->tenant = 'shop-a';
        $inside = $trail->record('orders', ['a' => 2], Facts::of('order.paid'));
        $own = $trail->record('orders', ['a' => 3], Facts::of('order.paid', tenant: 'shop-b'));
        $bare = $trail->record('orders', ['a' => 4]);
        $restore = $trail->recordDiscontinuity('orders', 'restored');

        self::assertSame([null, 'shop-a', 'shop-b', 'shop-a', null], [$outside->facts->tenant, $inside->facts->tenant, $own->facts->tenant, $bare->facts->tenant, $restore->facts->tenant]);
        self::assertSame(['order.paid', 'acct-7'], [$outside->facts->action, $outside->facts->actor]);
        self::assertNull($bare->facts->action);
        self::assertEquals(Facts::none(), $restore->facts);

        // A race is retried, and the notifier hears of the row once, when it is in.
        $this->rows->races = 2;
        $raced = $trail->record('orders', ['a' => 5], Facts::of('order.paid'));
        self::assertSame(8, $raced->sequence);
        self::assertSame(['orders#1', 'orders#2', 'orders#3', 'orders#4', 'orders#5', 'orders#8'], $notifier->told);
        self::assertSame('shop-a', $raced->facts->tenant);
    }

    public function testTheLogIsReadAPageAtATime(): void
    {
        $this->seed();
        $log = new AuditLog(new ScanningAuditLogReader($this->rows));

        $first = $log->page(AuditQuery::all()->limitedTo(2));
        self::assertSame(['orders#3', 'members#1'], $this->places($first->rows));
        self::assertFalse($first->isLast());

        $second = $log->page(AuditQuery::all()->limitedTo(2)->after($first->next));
        self::assertSame(['orders#2', 'orders#1'], $this->places($second->rows));
        self::assertTrue($second->isLast(), 'Exactly the limit is left: no next page.');

        self::assertSame(['orders#1', 'orders#2', 'members#1', 'orders#3'], $this->places($log->page(AuditQuery::all()->ordered(Order::OldestFirst))->rows));
        self::assertSame(['orders#3', 'orders#1'], $this->places($log->page(AuditQuery::all()->about(Subject::of('order', '42')))->rows));
        self::assertSame(['orders#1', 'orders#3'], $this->places($log->history(Subject::of('order', '42'))->rows));
        self::assertSame(['orders#1'], $this->places($log->history(Subject::of('order', '42'), limit: 1)->rows));
        $next = $log->history(Subject::of('order', '42'), limit: 1)->next;
        self::assertSame(['orders#3'], $this->places($log->history(Subject::of('order', '42'), $next, 1)->rows));
        self::assertSame(['orders#2'], $this->places($log->page(AuditQuery::all()->inSegment('orders')->byActor('acct-8'))->rows));
        self::assertSame([], $log->page(AuditQuery::all()->inSegment('payments'))->rows);

        self::assertSame('orders', $log->row('orders', 2)?->segment);
        self::assertNull($log->row('orders', 9));
        self::assertNull($log->row('payments', 1));

        self::assertSame(['orders#3', 'members#1', 'orders#2', 'orders#1'], $this->places(array_values([...$log->each(AuditQuery::all()->limitedTo(1))])));
        self::assertSame([], [...$log->each(AuditQuery::all()->inSegment('payments'))]);
    }

    public function testEveryRowOfALongLogIsExportedPageByPage(): void
    {
        $trail = new AuditTrail($this->rows, new JsonSerializer(), $this->clock);

        for ($i = 0; $i < AuditQuery::MAX_LIMIT + 1; ++$i) {
            $trail->record('orders', ['i' => $i]);
        }

        $log = new AuditLog(new ScanningAuditLogReader($this->rows));
        $this->rows->scans = 0;
        $rows = [...$log->each(AuditQuery::all()->ordered(Order::OldestFirst))];

        self::assertCount(AuditQuery::MAX_LIMIT + 1, $rows);
        self::assertSame([1, AuditQuery::MAX_LIMIT + 1], [$rows[0]->sequence, $rows[AuditQuery::MAX_LIMIT]->sequence]);
        self::assertSame(2, $this->rows->scans, 'Two pages of the largest size.');
    }

    public function testInATenantTheLogShowsItsRowsOnly(): void
    {
        $this->seed();
        $log = new AuditLog(new ScanningAuditLogReader($this->rows), $this->tenant);

        self::assertCount(4, $log->page(AuditQuery::all())->rows);
        self::assertNotNull($log->row('members', 1));

        $this->tenant->tenant = 'shop-a';
        self::assertSame(['orders#3', 'orders#1'], $this->places($log->page(AuditQuery::all())->rows));
        self::assertSame(['orders#3', 'orders#1'], $this->places($log->page(AuditQuery::all()->inTenant('shop-b'))->rows), 'A query cannot leave the tenant.');
        self::assertNull($log->row('members', 1));
        self::assertNull($log->row('orders', 2));
        self::assertNull($log->row('orders', 99), 'No row, in a tenant, is no row.');
        self::assertSame('shop-a', $log->row('orders', 1)?->facts->tenant);
    }

    public function testTheChainStatusOfEachSegment(): void
    {
        $this->seed();
        $status = new ChainStatus($this->rows, new TrailVerification($this->rows));

        $segments = $status->segments();
        self::assertSame(['members', 'orders'], array_map(static fn (SegmentStatus $segment): string => $segment->segment, $segments));
        self::assertSame([1, 3], array_map(static fn (SegmentStatus $segment): ?int => $segment->head?->sequence, $segments));
        self::assertSame([ChainState::Unverified, ChainState::Unverified], array_map(static fn (SegmentStatus $segment): ChainState => $segment->state(), $segments));
        self::assertSame(0, $this->rows->scans, 'Listing reads the heads only.');

        $orders = $status->verify('orders');
        self::assertSame([ChainState::Holds, 3], [$orders->state(), $orders->head?->sequence]);

        $this->rows->rows['orders'][2] = AuditRow::restore('orders', 2, RowKind::Event, new DateTimeImmutable(), 'test-1', '{}', AuditRow::GENESIS, str_repeat('0', 64));
        self::assertSame(ChainState::Broken, $status->verify('orders')->state());

        $empty = $status->verify('payments');
        self::assertSame([ChainState::Holds, null, 'payments'], [$empty->state(), $empty->head, $empty->segment]);
    }

    private function seed(): void
    {
        $trail = new AuditTrail($this->rows, new JsonSerializer(), $this->clock);
        $trail->record('orders', ['a' => 1], Facts::of('order.created', 'acct-7', Subject::of('order', '42'), 'shop-a'));

        $this->clock->modify('+1 second');
        $trail->record('orders', ['a' => 2], Facts::of('order.created', 'acct-8', Subject::of('order', '43'), 'shop-b'));
        $this->clock->modify('+1 second');
        $trail->record('members', ['a' => 3], Facts::of('member.joined', 'acct-8'));
        $trail->record('orders', ['a' => 4], Facts::of('order.paid', 'acct-7', Subject::of('order', '42'), 'shop-a'));
    }

    /**
     * @param list<AuditRow> $rows
     *
     * @return list<string>
     */
    private function places(array $rows): array
    {
        return array_map(static fn (AuditRow $row): string => $row->segment.'#'.$row->sequence, $rows);
    }
}
