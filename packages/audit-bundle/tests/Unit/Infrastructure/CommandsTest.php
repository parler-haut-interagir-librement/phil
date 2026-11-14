<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\AuditBundle\Application\Anchoring;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\DashboardFeed;
use Phil\AuditBundle\Application\TrailVerification;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\SiteSegment;
use Phil\AuditBundle\Infrastructure\Anchor\JsonLinesAnchorStore;
use Phil\AuditBundle\Infrastructure\Console\AnchorCommand;
use Phil\AuditBundle\Infrastructure\Console\FeedCommand;
use Phil\AuditBundle\Infrastructure\Console\VerifyCommand;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryAuditRows;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryCheckpoints;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use Phil\AuditBundle\Tests\Unit\Fixture\RecordingExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(AnchorCommand::class)]
#[CoversClass(VerifyCommand::class)]
#[CoversClass(FeedCommand::class)]
final class CommandsTest extends TestCase
{
    public function testTheTrailIsAnchoredThenVerifiedAndARewriteFailsLoudly(): void
    {
        $rows = new InMemoryAuditRows();
        $trail = new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-04 12:00 UTC'));
        $first = $trail->record('orders', ['order' => 1]);
        $head = $trail->record('orders', ['order' => 2]);
        $trail->record('members', ['member' => 'm-1']);
        $store = new JsonLinesAnchorStore(sys_get_temp_dir().'/phil-audit-'.bin2hex(random_bytes(4)).'.jsonl');

        $anchor = new AnchorCommand(new Anchoring($rows, $store), new MockClock('2026-11-04 23:59 UTC'));
        self::assertSame('phil:audit:anchor', $anchor->getName());
        $anchored = new CommandTester($anchor);
        self::assertSame(Command::SUCCESS, $anchored->execute([]));
        self::assertSame("members: {$rows->rows['members'][1]->hash}\norders: {$head->hash}\n2 segment(s) anchored.\n", $anchored->getDisplay());
        self::assertSame('2026-11-04T23:59:00+00:00', $store->anchors('orders')[0]->periodEnd->format('c'));

        $verify = new VerifyCommand(new TrailVerification($rows, $store));
        self::assertSame('phil:audit:verify', $verify->getName());
        $holds = new CommandTester($verify);
        self::assertSame(Command::SUCCESS, $holds->execute([]));
        self::assertSame("members: holds\norders: holds\n", $holds->getDisplay());

        $rows->rows['orders'][2] = AuditRow::append($first, 'orders', RowKind::Event, new DateTimeImmutable('2026-11-04 12:00 UTC'), 'test-1', '{"order":3}');
        $broken = new CommandTester($verify);
        self::assertSame(Command::FAILURE, $broken->execute([]));
        self::assertSame("members: holds\norders: no row has the head hash anchored at 2026-11-04T23:59:00+00:00\norders: BROKEN\n1 break(s): the audit trail does not hold.\n", $broken->getDisplay());

        $memberBroken = new InMemoryAuditRows();
        $memberBroken->rows['members'][1] = AuditRow::restore('members', 1, RowKind::Event, new DateTimeImmutable(), 'v', '{}', AuditRow::GENESIS, str_repeat('f', 64));
        $memberBroken->rows['orders'] = $rows->rows['orders'];
        unset($memberBroken->rows['orders'][2]);
        $first2 = new CommandTester(new VerifyCommand(new TrailVerification($memberBroken)));
        self::assertSame(Command::FAILURE, $first2->execute([]));
        self::assertStringEndsWith("members: BROKEN\norders: holds\n1 break(s): the audit trail does not hold.\n", $first2->getDisplay());

        $one = new CommandTester($verify);
        self::assertSame(Command::SUCCESS, $one->execute(['segments' => ['members']]));
        self::assertSame("members: holds\n", $one->getDisplay());

        $empty = new CommandTester(new AnchorCommand(new Anchoring(new InMemoryAuditRows(), $store), new NativeClock()));
        self::assertSame(Command::SUCCESS, $empty->execute([]));
        self::assertSame("0 segment(s) anchored.\n", $empty->getDisplay());
    }

    public function testAnAnchoredRestoreHoldsButIsSaid(): void
    {
        $rows = new InMemoryAuditRows();
        new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-04 12:00 UTC'))->record('orders', ['order' => 1]);
        new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-04 12:00 UTC'))->record('orders', ['order' => 2]);
        $store = new JsonLinesAnchorStore(sys_get_temp_dir().'/phil-audit-'.bin2hex(random_bytes(4)).'.jsonl');
        new Anchoring($rows, $store)->anchor(new DateTimeImmutable('2026-11-04 23:59 UTC'));

        unset($rows->rows['orders'][2]);
        new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-05 00:10 UTC'))->recordDiscontinuity('orders', 'restored from the backup of 2026-11-04');
        $verify = new CommandTester(new VerifyCommand(new TrailVerification($rows, $store)));

        self::assertSame(Command::FAILURE, $verify->execute([]));
        self::assertSame("orders: no row has the head hash anchored at 2026-11-04T23:59:00+00:00\norders #2: the discontinuity is covered by no anchor\norders: BROKEN\n2 break(s): the audit trail does not hold.\n", $verify->getDisplay());

        new Anchoring($rows, $store)->anchor(new DateTimeImmutable('2026-11-05 00:15 UTC'));

        self::assertSame(Command::FAILURE, $verify->execute([]), 'An explained break fails until it was reviewed.');
        self::assertSame("orders #2: no row has the head hash anchored at 2026-11-04T23:59:00+00:00: explained by this discontinuity\norders: holds, with 1 explained break(s) to review\n1 explained break(s) to review: run again with --accept-explained once they were.\n", $verify->getDisplay());
        self::assertSame(Command::SUCCESS, $verify->execute(['--accept-explained' => true]));
        self::assertSame("orders #2: no row has the head hash anchored at 2026-11-04T23:59:00+00:00: explained by this discontinuity\norders: holds, with 1 explained break(s) to review\n", $verify->getDisplay());

        $head = $rows->head('orders');
        self::assertInstanceOf(AuditRow::class, $head);
        $rows->rows['orders'][3] = AuditRow::restore('orders', 3, RowKind::Event, new DateTimeImmutable('2026-11-05 00:20 UTC'), 'test-1', '{}', $head->hash, str_repeat('a', 64));

        self::assertSame(Command::FAILURE, $verify->execute(['--accept-explained' => true]));
        self::assertSame("orders #3: the row was changed after it was written\norders #2: no row has the head hash anchored at 2026-11-04T23:59:00+00:00: explained by this discontinuity\norders: BROKEN\n1 break(s): the audit trail does not hold.\n", $verify->getDisplay(), 'An explained break does not make up for an alarm.');
    }

    public function testTheExplainedBreaksOfEverySegmentAreCounted(): void
    {
        $rows = new InMemoryAuditRows();
        $store = new JsonLinesAnchorStore(sys_get_temp_dir().'/phil-audit-'.bin2hex(random_bytes(4)).'.jsonl');
        $segments = ['members', 'orders'];

        foreach ($segments as $segment) {
            new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-04 12:00 UTC'))->record($segment, ['n' => 1]);
            new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-04 12:00 UTC'))->record($segment, ['n' => 2]);
        }

        new Anchoring($rows, $store)->anchor(new DateTimeImmutable('2026-11-04 23:59 UTC'));

        foreach ($segments as $segment) {
            unset($rows->rows[$segment][2]);
            new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-05 00:10 UTC'))->recordDiscontinuity($segment, 'restored');
        }

        new Anchoring($rows, $store)->anchor(new DateTimeImmutable('2026-11-05 00:15 UTC'));
        $verify = new CommandTester(new VerifyCommand(new TrailVerification($rows, $store)));

        self::assertSame(Command::FAILURE, $verify->execute([]));
        self::assertStringEndsWith("2 explained break(s) to review: run again with --accept-explained once they were.\n", $verify->getDisplay());
    }

    public function testOneSiteIsVerifiedOnItsOwn(): void
    {
        $rows = new InMemoryAuditRows();
        $trail = new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-12 09:00 UTC'), siteSegments: true);
        $trail->record('orders', [], Facts::of('order.created', null, null, 'acme'));
        $trail->record('orders', [], Facts::of('order.created', null, null, 'globex'));

        $verify = new CommandTester(new VerifyCommand(new TrailVerification($rows)));

        self::assertSame(Command::SUCCESS, $verify->execute(['--tenant' => 'acme']));
        self::assertSame(SiteSegment::of('orders', 'acme').": holds\n", $verify->getDisplay());

        self::assertSame(Command::SUCCESS, $verify->execute(['--tenant' => 'initech']));
        self::assertSame("No segment to verify.\n", $verify->getDisplay());

        self::assertSame(Command::SUCCESS, $verify->execute([]));
        self::assertSame(2, substr_count($verify->getDisplay(), ': holds'));
    }

    public function testTheFeedIsRunBounded(): void
    {
        $rows = new InMemoryAuditRows();
        $trail = new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-12 09:00 UTC'));

        foreach (range(1, 3) as $order) {
            $trail->record('orders', ['order' => $order]);
        }

        $trail->record('members', ['member' => 1]);
        $exporter = new RecordingExporter();
        $command = new FeedCommand(new DashboardFeed($rows, $exporter, new InMemoryCheckpoints()));
        $feed = new CommandTester($command);

        self::assertSame('phil:audit:feed', $command->getName());
        self::assertSame(Command::SUCCESS, $feed->execute(['segments' => ['orders'], '--max' => '2']));
        self::assertSame("orders: 2\n2 fact(s) sent.\n", $feed->getDisplay());
        self::assertSame(Command::SUCCESS, $feed->execute([]));
        self::assertSame("members: 1\norders: 1\n2 fact(s) sent.\n", $feed->getDisplay());
        self::assertSame(Command::SUCCESS, $feed->execute([]));
        self::assertSame("0 fact(s) sent.\n", $feed->getDisplay());
        self::assertSame('100000', $command->getDefinition()->getOption('max')->getDefault());

        foreach (['0', '-1', 'ten', '1000000000', '01', "5\n"] as $max) {
            self::assertSame(Command::INVALID, $feed->execute(['--max' => $max]), $max);
            self::assertSame("--max is a whole number of rows, from 1 to 999999999.\n", $feed->getDisplay());
        }

        self::assertCount(3, $exporter->batches, 'Orders 1 and 2, then member 1, then order 3.');
    }
}
