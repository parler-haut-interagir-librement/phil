<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Unit;

use DateTimeImmutable;
use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\Contract\MembershipStore;
use Phil\AudienceSegments\Event\MemberEntered;
use Phil\AudienceSegments\Event\MemberLeft;
use Phil\AudienceSegments\Exception\PortContractViolated;
use Phil\AudienceSegments\Recalculation;
use Phil\AudienceSegments\RecalculationReport;
use Phil\AudienceSegments\Segment;
use Phil\AudienceSegments\Segments;
use Phil\AudienceSegments\Tests\Support\FixedClock;
use Phil\AudienceSegments\Tests\Support\MemoryAudience;
use Phil\AudienceSegments\Tests\Support\MemoryExclusions;
use Phil\AudienceSegments\Tests\Support\MemoryFacts;
use Phil\AudienceSegments\Tests\Support\MemoryMembershipStore;
use Phil\AudienceSegments\Tests\Support\RecordingDispatcher;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Exception\InvalidLimit;
use Phil\Rules\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Recalculation::class)]
#[CoversClass(RecalculationReport::class)]
#[CoversClass(MemberEntered::class)]
#[CoversClass(MemberLeft::class)]
#[CoversClass(PortContractViolated::class)]
final class RecalculationTest extends TestCase
{
    private MemoryFacts $facts;

    private Segment $supporters;

    private MemoryMembershipStore $store;

    private RecordingDispatcher $dispatcher;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->facts = new MemoryFacts();
        $this->supporters = new Segment('gold', 'Gold supporters', new Rule(new Leaf('supporter.active', ['tiers' => ['gold']]), AudienceConditions::types(supporters: $this->facts)));
        $this->store = new MemoryMembershipStore();
        $this->dispatcher = new RecordingDispatcher();
        $this->now = new DateTimeImmutable('2026-11-21 03:00');
    }

    public function testAFirstRunEntersEveryMember(): void
    {
        $this->facts->supporters = ['m1' => 'gold', 'm2' => 'silver', 'm3' => 'gold'];

        $report = $this->recalculation(new MemoryAudience('m1', 'm2', 'm3'))->recalculate($this->supporters);

        self::assertSame(['m1', 'm3'], $this->store->members('gold', null, 10));
        self::assertEquals([new MemberEntered('gold', 'm1', $this->now), new MemberEntered('gold', 'm3', $this->now)], $this->dispatcher->events);
        self::assertSame(['gold', 2, 2, 0], [$report->segment, $report->members, $report->entered, $report->left]);
        self::assertSame($this->now, $report->at);
        self::assertSame($this->now, $this->store->lastRecalculation('gold'));
    }

    public function testFindsWhoEnteredAndWhoLeft(): void
    {
        $this->store->add('gold', ['m1', 'm2', 'm4', 'm9']);
        $this->store->writes = [];
        // m1 stays, m2 lost the tier, m3 joined, m4 unsubscribed, m9 left the audience, m5 was never in.
        $this->facts->supporters = ['m1' => 'gold', 'm2' => 'silver', 'm3' => 'gold', 'm4' => 'gold'];

        $report = $this->recalculation(new MemoryAudience('m1', 'm2', 'm3', 'm4', 'm5'), new MemoryExclusions('m4'))->recalculate($this->supporters);

        self::assertSame(['m1', 'm3'], $this->store->members('gold', null, 10));
        self::assertEquals([
            new MemberEntered('gold', 'm3', $this->now),
            new MemberLeft('gold', 'm2', $this->now),
            new MemberLeft('gold', 'm4', $this->now),
            new MemberLeft('gold', 'm9', $this->now),
        ], $this->dispatcher->events);
        self::assertSame([2, 1, 3], [$report->members, $report->entered, $report->left]);
    }

    public function testChangesNothingWhenNothingChanged(): void
    {
        $this->store->add('gold', ['m1']);
        $this->store->writes = [];

        $this->facts->supporters = ['m1' => 'gold'];

        $report = $this->recalculation(new MemoryAudience('m1', 'm2'))->recalculate($this->supporters);

        self::assertSame([], $this->store->writes);
        self::assertSame([], $this->dispatcher->events);
        self::assertSame([1, 0, 0], [$report->members, $report->entered, $report->left]);
    }

    public function testWritesByBatches(): void
    {
        $this->store->add('gold', ['z1', 'z2', 'z3']);
        $this->store->writes = [];

        $this->facts->supporters = ['m1' => 'gold', 'm2' => 'gold', 'm3' => 'gold'];

        $this->recalculation(new MemoryAudience('m1', 'm2', 'm3'), batchSize: 2)->recalculate($this->supporters);

        self::assertSame([
            ['add', 'gold', ['m1', 'm2']],
            ['add', 'gold', ['m3']],
            ['remove', 'gold', ['z1', 'z2']],
            ['remove', 'gold', ['z3']],
        ], $this->store->writes);
    }

    public function testWritesByBatchesOfFiveHundredByDefault(): void
    {
        $ids = array_map(static fn (int $n): string => \sprintf('m%04d', $n), range(1, 501));
        $this->facts->supporters = array_fill_keys($ids, 'gold');
        $clock = new FixedClock($this->now);

        new Recalculation(new Segments(new MemoryAudience(...$ids), new MemoryExclusions(), $clock), $this->store, $this->dispatcher, $clock)->recalculate($this->supporters);

        self::assertSame([500, 1], array_map(static fn (array $write): int => \count($write[2]), $this->store->writes));
    }

    public function testReadsTheStoreToItsLastFullPage(): void
    {
        $this->store->add('gold', ['m1', 'm2']);

        $report = $this->recalculation(new MemoryAudience(), batchSize: 2)->recalculate($this->supporters);

        self::assertSame(2, $report->left);
    }

    /**
     * @return iterable<string, array{list<list<string>>, string}>
     */
    public static function brokenStores(): iterable
    {
        yield 'out of order' => [[['m2', 'm1']], 'MembershipStore gave "m1" out of ascending byte order, or at or before its cursor.'];
        yield 'back to the cursor' => [[['m1', 'm2'], ['m2']], 'MembershipStore gave "m2" out of ascending byte order'];
        yield 'too many' => [[['m1', 'm2', 'm3']], 'MembershipStore gave 3 members when 2 were asked.'];
    }

    /**
     * @param list<list<string>> $pages
     */
    #[DataProvider('brokenStores')]
    public function testStopsOnAStoreThatBreaksItsContract(array $pages, string $message): void
    {
        $store = new class($pages) implements MembershipStore {
            /**
             * @param list<list<string>> $pages
             */
            public function __construct(private array $pages)
            {
            }

            public function members(string $segment, ?string $after, int $limit): array
            {
                return array_shift($this->pages) ?? [];
            }

            public function add(string $segment, array $members): void
            {
            }

            public function remove(string $segment, array $members): void
            {
            }

            public function recalculated(string $segment, DateTimeImmutable $at): void
            {
            }

            public function lastRecalculation(string $segment): ?DateTimeImmutable
            {
                return null;
            }
        };

        $this->expectException(PortContractViolated::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Recalculation(new Segments(new MemoryAudience(), new MemoryExclusions(), new FixedClock()), $store, $this->dispatcher, new FixedClock(), 2)->recalculate($this->supporters);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badBatches(): iterable
    {
        yield 'none' => [0];
        yield 'too large' => [1001];
    }

    #[DataProvider('badBatches')]
    public function testRefusesABatchSizeOutOfRange(int $batchSize): void
    {
        $this->expectException(InvalidLimit::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('batchSize is %d; it must lie between 1 and 1000.', $batchSize));

        $this->recalculation(new MemoryAudience(), batchSize: $batchSize);
    }

    public function testAcceptsTheWidestBatches(): void
    {
        $this->facts->supporters = ['m1' => 'gold'];

        self::assertSame(1, $this->recalculation(new MemoryAudience('m1'), batchSize: 1000)->recalculate($this->supporters)->members);
        self::assertSame(0, $this->recalculation(new MemoryAudience('m1'), batchSize: 1)->recalculate($this->supporters)->entered);
    }

    private function recalculation(MemoryAudience $audience, ?MemoryExclusions $exclusions = null, int $batchSize = 500): Recalculation
    {
        $clock = new FixedClock($this->now);

        return new Recalculation(new Segments($audience, $exclusions ?? new MemoryExclusions(), $clock, 2), $this->store, $this->dispatcher, $clock, $batchSize);
    }
}
