<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Application;

use DateTimeImmutable;
use LogicException;
use Override;
use Phil\AuditBundle\Application\Anchoring;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\TrailVerification;
use Phil\AuditBundle\Domain\Chain\Anchor;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\ChainBreak;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Contract\AnchorReader;
use Phil\AuditBundle\Domain\Contract\AnchorWriter;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Exception\ChainConflict;
use Phil\AuditBundle\Tests\Unit\Fixture\FixedTenant;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryAuditRows;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

#[CoversClass(AuditTrail::class)]
#[CoversClass(Anchoring::class)]
#[CoversClass(TrailVerification::class)]
final class TrailTest extends TestCase
{
    private InMemoryAuditRows $rows;

    private MockClock $clock;

    /** @var list<Anchor> */
    private array $written = [];

    #[Override]
    protected function setUp(): void
    {
        $this->rows = new InMemoryAuditRows();
        $this->clock = new MockClock('2026-11-04 12:00 UTC');
    }

    public function testEventsAreChainedPerSegment(): void
    {
        $trail = new AuditTrail($this->rows, new JsonSerializer(), $this->clock);
        $first = $trail->record('orders', ['order' => 7, 'amount' => '10.00']);
        $this->clock->modify('+1 minute');
        $second = $trail->record('orders', ['order' => 7, 'state' => 'paid']);
        $other = $trail->record('members', ['member' => 'm-1']);
        $restore = $trail->recordDiscontinuity('orders', 'restored from the backup of 2026-11-03');

        self::assertSame([1, 2, 1, 3], [$first->sequence, $second->sequence, $other->sequence, $restore->sequence]);
        self::assertSame(['{"amount":"10.00","order":7}', 'test-1', '2026-11-04T12:00:00+00:00'], [$first->payload, $first->version, $first->recordedAt->format('c')]);
        self::assertSame([$first->hash, $second->hash], [$second->previousHash, $restore->previousHash]);
        self::assertSame([RowKind::Discontinuity, '{"discontinuity":"restored from the backup of 2026-11-03"}'], [$restore->kind, $restore->payload]);
        self::assertSame(['members', 'orders'], $this->rows->segments());
        self::assertEqualsWithDelta(time(), new AuditTrail($this->rows, new JsonSerializer(), new NativeClock())->record('orders', [])->recordedAt->getTimestamp(), 5);
    }

    public function testAReadIsRecordedWithItsFacts(): void
    {
        $trail = new AuditTrail($this->rows, new JsonSerializer(), $this->clock, new FixedTenant('shop-a'));
        $read = $trail->recordRead('members', 'account.profile', Subject::of('account', 'acct-9'), 'acct-7', ['reason' => 'support']);
        $bare = $trail->recordRead('members', 'account.export', Subject::of('account', 'acct-9'));

        self::assertSame(['read.account.profile', 'acct-7', 'account', 'acct-9', 'shop-a'], $read->facts->hashLines());
        self::assertSame(['{"reason":"support"}', '[]'], [$read->payload, $bare->payload]);
        self::assertSame(['read.account.export', null], [$bare->facts->action, $bare->facts->actor]);
        self::assertTrue($read->facts->isRead());
        self::assertSame([1, 2], [$read->sequence, $bare->sequence]);
    }

    public function testARaceIsRetriedOnTheNewHead(): void
    {
        $trail = new AuditTrail($this->rows, new JsonSerializer(), $this->clock);
        $this->rows->races = 2;

        $row = $trail->record('orders', ['order' => 1]);

        self::assertSame(3, $row->sequence, 'Two rows came first: the third place is this one.');
        self::assertSame($this->rows->rows['orders'][2]->hash, $row->previousHash);
        self::assertSame(0, $this->rows->races);

        $this->rows->rows = [];
        $this->rows->races = 3;
        $this->expectException(ChainConflict::class);
        $trail->record('orders', ['order' => 1]);
    }

    public function testEverySegmentIsAnchoredThenVerified(): void
    {
        $trail = new AuditTrail($this->rows, new JsonSerializer(), $this->clock);
        $first = $trail->record('orders', ['order' => 1]);
        $head = $trail->record('orders', ['order' => 2]);
        $member = $trail->record('members', ['member' => 'm-1']);
        $writer = new readonly class($this) implements AnchorWriter {
            public function __construct(
                private TrailTest $test,
            ) {
            }

            public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void
            {
                $this->test->wrote(new Anchor($segment, $periodEnd, $firstHash, $headHash));
            }
        };
        $at = new DateTimeImmutable('2026-11-04 23:59 UTC');

        self::assertSame(['members' => $member->hash, 'orders' => $head->hash], new Anchoring($this->rows, $writer)->anchor($at));
        self::assertEquals([new Anchor('members', $at, $member->hash, $member->hash), new Anchor('orders', $at, $first->hash, $head->hash)], $this->written);
        self::assertSame(0, $this->rows->scans, 'Anchoring reads the two ends of a segment, not its rows.');

        $reader = new readonly class($this->written) implements AnchorReader {
            /**
             * @param list<Anchor> $anchors
             */
            public function __construct(
                private array $anchors,
            ) {
            }

            public function anchors(string $segment): array
            {
                return array_values(array_filter($this->anchors, static fn (Anchor $anchor): bool => $anchor->segment === $segment));
            }
        };
        self::assertSame(['members' => [], 'orders' => []], new TrailVerification($this->rows, $reader)->verify());

        $this->rows->rows['orders'][2] = AuditRow::append($first, 'orders', RowKind::Event, new DateTimeImmutable(), 'test-1', '{"order":3}');
        self::assertSame(['orders' => ['orders: no row has the head hash anchored at 2026-11-04T23:59:00+00:00']], array_map(static fn (array $breaks): array => array_map(static fn (ChainBreak $break): string => $break->toString(), $breaks), new TrailVerification($this->rows, $reader)->verify(['orders'])));
        self::assertSame(['orders' => []], new TrailVerification($this->rows)->verify(['orders']));
    }

    public function testNothingToAnchorAnchorsNothing(): void
    {
        $writer = new class implements AnchorWriter {
            public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void
            {
                throw new LogicException('Nothing should be anchored.');
            }
        };

        self::assertSame([], new Anchoring($this->rows, $writer)->anchor(new DateTimeImmutable()));
        self::assertSame([], new TrailVerification($this->rows)->verify());
    }

    /**
     * The first row of a segment is read, then its head: a segment whose first row is written between the two reads
     * has a head and no first row yet, and is anchored the next time.
     */
    public function testASegmentWithoutBothEndsIsNotAnchored(): void
    {
        new AuditTrail($this->rows, new JsonSerializer(), $this->clock)->record('orders', ['order' => 1]);
        $racing = new readonly class($this->rows) implements AuditRowRepository {
            public function __construct(
                private AuditRowRepository $rows,
            ) {
            }

            public function first(string $segment): ?AuditRow
            {
                return null;
            }

            public function head(string $segment): ?AuditRow
            {
                return $this->rows->head($segment);
            }

            public function append(AuditRow $row): void
            {
                $this->rows->append($row);
            }

            public function rows(string $segment): iterable
            {
                return $this->rows->rows($segment);
            }

            public function rowsAfter(string $segment, int $sequence, int $limit): array
            {
                return $this->rows->rowsAfter($segment, $sequence, $limit);
            }

            public function segments(): array
            {
                return $this->rows->segments();
            }
        };
        $writer = new class implements AnchorWriter {
            public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void
            {
                throw new LogicException('Nothing should be anchored.');
            }
        };

        self::assertSame([], new Anchoring($racing, $writer)->anchor(new DateTimeImmutable()));
    }

    public function wrote(Anchor $anchor): void
    {
        $this->written[] = $anchor;
    }
}
