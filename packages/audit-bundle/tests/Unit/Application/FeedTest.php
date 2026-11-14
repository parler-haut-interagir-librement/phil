<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Application;

use Override;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\DashboardFeed;
use Phil\AuditBundle\Application\Exception\ExportFailed;
use Phil\AuditBundle\Application\Message\AuditRecorded;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Export\ExportedFact;
use Phil\AuditBundle\Infrastructure\Messenger\FeedOnRecorded;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryAuditRows;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryCheckpoints;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use Phil\AuditBundle\Tests\Unit\Fixture\RecordingExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DashboardFeed::class)]
#[CoversClass(ExportedFact::class)]
#[CoversClass(ExportFailed::class)]
#[CoversClass(FeedOnRecorded::class)]
final class FeedTest extends TestCase
{
    private InMemoryAuditRows $rows;

    private AuditTrail $trail;

    private RecordingExporter $exporter;

    private InMemoryCheckpoints $checkpoints;

    #[Override]
    protected function setUp(): void
    {
        $this->rows = new InMemoryAuditRows();
        $this->trail = new AuditTrail($this->rows, new JsonSerializer(), new MockClock('2026-11-12 09:00:00.123456 UTC'));
        $this->exporter = new RecordingExporter();
        $this->checkpoints = new InMemoryCheckpoints();
    }

    public function testAFactCarriesIdentifiersAndNoPayload(): void
    {
        $row = $this->trail->record('orders', ['email' => 'ada@example.org'], Facts::of('order.paid', 'acct-7', Subject::of('order', '42'), 'Shop A'));
        $fact = ExportedFact::of($row);

        self::assertSame([
            'segment' => 'orders',
            'sequence' => 1,
            'recorded_at' => '2026-11-12T09:00:00.123456Z',
            'kind' => 'event',
            'action' => 'order.paid',
            'actor' => 'acct-7',
            'subject_type' => 'order',
            'subject_id' => '42',
            'tenant' => 'Shop A',
            'hash' => $row->hash,
        ], $fact->toArray());
        self::assertSame('1794474000123456000', $fact->unixNanos);
        self::assertStringNotContainsString('ada@', (string) json_encode($fact), 'The payload stays in the trail.');

        $bare = ExportedFact::of($this->trail->recordDiscontinuity('orders', 'restored'));
        self::assertSame(['discontinuity', null, null, null, null, null], [$bare->kind, $bare->action, $bare->actor, $bare->subjectType, $bare->subjectId, $bare->tenant]);
    }

    public function testTheFeedSendsWhatEachSegmentRecordedSinceItsPlace(): void
    {
        foreach (range(1, 5) as $order) {
            $this->trail->record('orders', ['order' => $order]);
        }

        $this->trail->record('members', ['member' => 1]);
        $feed = new DashboardFeed($this->rows, $this->exporter, $this->checkpoints, 'kpi', 2);

        self::assertSame(['members' => 1, 'orders' => 5], $feed->feed());
        self::assertSame(['members#1', 'orders#1', 'orders#2', 'orders#3', 'orders#4', 'orders#5'], $this->exporter->places());
        self::assertSame([1, 2, 2, 1], array_map(\count(...), $this->exporter->batches), 'A batch at a time.');
        self::assertSame(['kpi|members' => 1, 'kpi|orders' => 5], $this->checkpoints->places);
        self::assertSame([], $feed->feed(), 'Nothing twice.');
        self::assertSame(0, $feed->lag('orders'));

        $this->trail->record('orders', ['order' => 6]);
        self::assertSame(1, $feed->lag('orders'));
        self::assertSame(0, $feed->lag('payments'));
        self::assertSame(['orders' => 1], $feed->feed(['orders', 'payments']));
        self::assertSame('orders#6', $this->exporter->places()[6]);

        $other = new DashboardFeed($this->rows, $this->exporter, $this->checkpoints);
        self::assertSame(6, $other->lag('orders'), 'Another feed keeps another place.');
        self::assertSame(['members' => 1, 'orders' => 6], $other->feed());
        self::assertSame([1, 6], [$this->checkpoints->position('dashboards', 'members'), $this->checkpoints->position('dashboards', 'orders')]);
    }

    public function testARunIsBounded(): void
    {
        foreach (range(1, 5) as $order) {
            $this->trail->record('orders', ['order' => $order]);
            $this->trail->record('members', ['member' => $order]);
        }

        $feed = new DashboardFeed($this->rows, $this->exporter, $this->checkpoints, batch: 500);

        self::assertSame(['members' => 3], $feed->feed(max: 3));
        self::assertSame([3], array_map(\count(...), $this->exporter->batches), 'No more read than the run may send.');
        self::assertSame(['members' => 2, 'orders' => 2], $feed->feed(max: 4));
        self::assertSame(['orders' => 3], $feed->feed(max: 4));
        self::assertSame(0, $feed->feedSegment('orders', 2), 'Nothing left.');
        self::assertSame(500, DashboardFeed::DEFAULT_BATCH);
    }

    public function testARunReadsNoMoreThanItMaySendAndNeverReadsForNothing(): void
    {
        foreach (range(1, 5) as $order) {
            $this->trail->record('orders', ['order' => $order]);
        }

        $feed = new DashboardFeed($this->rows, $this->exporter, $this->checkpoints, batch: 2);

        self::assertSame(3, $feed->feedSegment('orders', 3));
        self::assertSame([2, 1], $this->rows->limits, 'A batch, then what is left of the run.');
        self::assertSame([2, 1], array_map(\count(...), $this->exporter->batches));

        $this->rows->limits = [];
        self::assertSame(2, $feed->feedSegment('orders', 2));
        self::assertSame([2], $this->rows->limits, 'Nothing is read once the run has sent what it may.');
    }

    public function testTheSegmentsNamedAreTheOnlyOnesFed(): void
    {
        $this->trail->record('orders', ['order' => 1]);
        $this->trail->record('members', ['member' => 1]);

        $feed = new DashboardFeed($this->rows, $this->exporter, $this->checkpoints);

        self::assertSame(['orders' => 1], $feed->feed(['orders']));
        self::assertSame(['orders#1'], $this->exporter->places());
    }

    public function testARunOfOneRowSendsOneRow(): void
    {
        $this->trail->record('orders', ['order' => 1]);
        $this->trail->record('orders', ['order' => 2]);

        self::assertSame(['orders' => 1], new DashboardFeed($this->rows, $this->exporter, $this->checkpoints)->feed(max: 1));
    }

    public function testTheExporterGetsFactsAndAPlaceAheadOfTheHeadLeavesNoLag(): void
    {
        $this->trail->record('orders', ['order' => 1]);
        $feed = new DashboardFeed($this->rows, $this->exporter, $this->checkpoints);
        $feed->feed();

        foreach ($this->exporter->batches as $batch) {
            self::assertContainsOnlyInstancesOf(ExportedFact::class, $batch);
        }

        $this->checkpoints->advance('dashboards', 'orders', 9);
        self::assertSame(0, $feed->lag('orders'));
    }

    public function testAFailureCarriesNoCode(): void
    {
        self::assertSame(0, ExportFailed::because('down')->getCode());
    }

    public function testTheDefaultBatchIsFiveHundredRows(): void
    {
        foreach (range(1, 501) as $order) {
            $this->trail->record('orders', ['order' => $order]);
        }

        new DashboardFeed($this->rows, $this->exporter, $this->checkpoints)->feed();

        self::assertSame([500, 1], array_map(\count(...), $this->exporter->batches));
    }

    public function testAFailedBatchIsSentAgainNextTime(): void
    {
        foreach (range(1, 3) as $order) {
            $this->trail->record('orders', ['order' => $order]);
        }

        $feed = new DashboardFeed($this->rows, $this->exporter, $this->checkpoints, batch: 2);
        $this->exporter->failures = 1;

        try {
            $feed->feed();
            self::fail('A failed batch was taken for sent.');
        } catch (ExportFailed $exportFailed) {
            self::assertSame('The dashboard is down.', $exportFailed->getMessage());
        }

        self::assertSame(3, $feed->lag('orders'));
        self::assertSame(['orders' => 3], $feed->feed());
        self::assertSame(['orders#1', 'orders#2', 'orders#3'], $this->exporter->places());
    }

    public function testEachRecordedMessageFeedsItsSegment(): void
    {
        $this->trail->record('orders', ['order' => 1]);
        $this->trail->record('members', ['member' => 1]);

        $handler = new FeedOnRecorded(new DashboardFeed($this->rows, $this->exporter, $this->checkpoints));

        $handler(new AuditRecorded('orders', 1));
        $handler(new AuditRecorded('orders', 1));

        self::assertSame(['orders#1'], $this->exporter->places(), 'Its segment only, once.');
    }
}
