<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Application;

use Override;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\ChainStatus;
use Phil\AuditBundle\Application\TrailVerification;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\ChainBreak;
use Phil\AuditBundle\Domain\Chain\ChainVerifier;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\SiteSegment;
use Phil\AuditBundle\Domain\Read\ChainState;
use Phil\AuditBundle\Domain\Read\SegmentStatus;
use Phil\AuditBundle\Tests\Unit\Fixture\FixedTenant;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryAuditRows;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * A trail shared by several sites: each tenant's rows chained in a segment of its own, verified on its own, and shown
 * to that tenant only.
 */
#[CoversClass(AuditTrail::class)]
#[CoversClass(ChainVerifier::class)]
#[CoversClass(ChainStatus::class)]
#[CoversClass(TrailVerification::class)]
#[CoversClass(SegmentStatus::class)]
final class SitesTest extends TestCase
{
    private InMemoryAuditRows $rows;

    private FixedTenant $tenant;

    private AuditTrail $trail;

    #[Override]
    protected function setUp(): void
    {
        $this->rows = new InMemoryAuditRows();
        $this->tenant = new FixedTenant();
        $this->trail = new AuditTrail($this->rows, new JsonSerializer(), new MockClock('2026-11-12 09:00 UTC'), $this->tenant, siteSegments: true);
    }

    public function testEachTenantHasAChainOfItsOwn(): void
    {
        $this->tenant->tenant = 'acme';
        $acme = $this->trail->record('orders', ['order' => 1]);
        $named = $this->trail->record('orders', ['order' => 2], Facts::of('order.paid', null, null, 'globex'));
        $this->tenant->tenant = null;
        $shared = $this->trail->record('orders', ['order' => 3]);

        self::assertSame([SiteSegment::of('orders', 'acme'), 1, 'acme'], [$acme->segment, $acme->sequence, $acme->facts->tenant]);
        self::assertSame([SiteSegment::of('orders', 'globex'), 1, 'globex'], [$named->segment, $named->sequence, $named->facts->tenant]);
        self::assertSame(['orders', 1, null], [$shared->segment, $shared->sequence, $shared->facts->tenant]);
        self::assertSame(SiteSegment::of('orders', 'acme'), $this->trail->segmentFor('orders', 'acme'));
        self::assertSame('orders', $this->trail->segmentFor('orders', null));
        self::assertSame('orders', new AuditTrail($this->rows, new JsonSerializer(), new MockClock())->segmentFor('orders', 'acme'), 'One segment for every site unless asked.');
    }

    public function testARestoreOfOneSiteIsRecordedInItsSegment(): void
    {
        $this->trail->record('orders', [], Facts::of('order.created', null, null, 'acme'));
        $restore = $this->trail->recordDiscontinuity('orders', 'restored', 'acme');
        $shared = $this->trail->recordDiscontinuity('orders', 'restored');

        self::assertSame([SiteSegment::of('orders', 'acme'), 2, RowKind::Discontinuity, 'acme'], [$restore->segment, $restore->sequence, $restore->kind, $restore->facts->tenant]);
        self::assertSame(['orders', null], [$shared->segment, $shared->facts->tenant]);
        self::assertSame([], new TrailVerification($this->rows)->verify()[$restore->segment]);
    }

    public function testARowOfAnotherSiteInASiteSegmentIsABreak(): void
    {
        $acme = $this->trail->record('orders', [], Facts::of('order.created', null, null, 'acme'));
        $this->rows->rows[$acme->segment][2] = AuditRow::append($acme, $acme->segment, RowKind::Event, $acme->recordedAt, 'test-1', '{}', Facts::of('order.created', null, null, 'globex'));
        $this->rows->rows[$acme->segment][3] = AuditRow::append($this->rows->rows[$acme->segment][2], $acme->segment, RowKind::Event, $acme->recordedAt, 'test-1', '{}');

        $breaks = new TrailVerification($this->rows)->verify()[$acme->segment];

        self::assertSame([[2, 'the row is not of the site the segment belongs to'], [3, 'the row is not of the site the segment belongs to']], array_map(static fn (ChainBreak $break): array => [$break->sequence, $break->reason], $breaks));
        self::assertTrue($breaks[0]->isAlarm());
    }

    public function testOneVerifierJudgesEachSegmentByItsOwnSite(): void
    {
        $acme = $this->trail->record('orders', [], Facts::of('order.created', null, null, 'acme'));
        $globex = AuditRow::append($acme, $acme->segment, RowKind::Event, $acme->recordedAt, 'test-1', '{}', Facts::of('order.created', null, null, 'globex'));
        $again = AuditRow::append($globex, $acme->segment, RowKind::Event, $acme->recordedAt, 'test-1', '{}', Facts::of('order.created', null, null, 'acme'));
        $shared = $this->trail->record('orders', []);
        $verifier = new ChainVerifier();

        self::assertSame([2], array_map(static fn (ChainBreak $break): ?int => $break->sequence, $verifier->verify($acme->segment, [$acme, $globex, $again], [])));
        self::assertSame([], $verifier->verify('orders', [$shared], []), 'A shared segment after a site segment: no site to hold to.');
        self::assertSame([1], array_map(static fn (ChainBreak $break): ?int => $break->sequence, $verifier->verify(SiteSegment::of('orders', 'globex'), [AuditRow::append(null, SiteSegment::of('orders', 'globex'), RowKind::Event, $acme->recordedAt, 'test-1', '{}', Facts::of('order.created', null, null, 'acme'))], [])), 'What was admitted in another site is not admitted here.');
    }

    public function testATenantSeesAndVerifiesItsOwnSegmentsOnly(): void
    {
        $this->trail->record('orders', [], Facts::of('order.created', null, null, 'acme'));
        $this->trail->record('orders', [], Facts::of('order.created', null, null, 'globex'));
        $this->trail->record('orders', []);

        $acme = SiteSegment::of('orders', 'acme');
        $globex = SiteSegment::of('orders', 'globex');
        $status = new ChainStatus($this->rows, new TrailVerification($this->rows), $this->tenant);

        self::assertCount(3, new ChainStatus($this->rows, new TrailVerification($this->rows), new FixedTenant())->segments());
        $this->tenant->tenant = 'acme';
        self::assertSame([$acme], array_map(static fn (SegmentStatus $segment): string => $segment->segment, $status->segments()));
        self::assertSame(ChainState::Holds, $status->verify($acme)->state());
        self::assertSame(1, $status->verify($acme)->head?->sequence);

        $hidden = $status->verify($globex);
        self::assertSame([null, [], ChainState::Holds], [$hidden->head, $hidden->breaks, $hidden->state()], 'Another site reads as a segment without rows.');
        self::assertNull($status->verify('orders')->head);
        $this->rows->scans = 0;
        $status->verify('orders');
        self::assertSame(0, $this->rows->scans, 'Not read at all.');

        self::assertSame([$acme], array_keys(new TrailVerification($this->rows)->verify(null, 'acme')));
        self::assertSame([], new TrailVerification($this->rows)->verify(['orders', $globex], 'acme'));
        self::assertSame(['orders', $globex], array_keys(new TrailVerification($this->rows)->verify(['orders', $globex])));
    }

    public function testASegmentStatusNamesItsSite(): void
    {
        $acme = $this->trail->record('orders', [], Facts::of('order.created', null, null, 'acme'));
        $shared = $this->trail->record('orders', [], Facts::of('order.created'));
        $foreign = AuditRow::append(null, 'members', RowKind::Event, $acme->recordedAt, 'test-1', '{}', Facts::of('member.joined', null, null, 'acme'));

        self::assertSame(['acme', 'orders'], [new SegmentStatus($acme->segment, $acme)->site(), new SegmentStatus($acme->segment, $acme)->base()]);
        self::assertSame([null, 'orders'], [new SegmentStatus('orders', $shared)->site(), new SegmentStatus('orders', $shared)->base()]);
        self::assertNull(new SegmentStatus('members', $foreign)->site(), 'A shared segment has no site, whatever its head says.');
        self::assertNull(new SegmentStatus($acme->segment, null)->site());
    }
}
