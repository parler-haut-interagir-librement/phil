<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Outbox\Exception\InvalidSetting;
use Phil\Outbox\Housekeeping;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use Phil\Outbox\Tests\Unit\Fixture\SpyStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Housekeeping::class)]
final class HousekeepingTest extends TestCase
{
    private SpyStore $store;

    private Housekeeping $housekeeping;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new SpyStore();
        $this->housekeeping = new Housekeeping($this->store, new MockClock('2026-11-17 12:00:00', 'UTC'));
    }

    public function testTheDeadRecordsAreListedFoundAndCounted(): void
    {
        $dead = $this->records(3, RecordState::Dead);
        $this->records(1, RecordState::Pending);
        $this->records(2, RecordState::Delivered);

        self::assertSame([$dead[0]->value, $dead[1]->value, $dead[2]->value], $this->ids($this->housekeeping->dead()));
        self::assertSame([$dead[1]->value], $this->ids($this->housekeeping->dead(1, 1)));
        self::assertSame($dead[0]->value, $this->housekeeping->find($dead[0])?->id->value);
        $this->records(4, RecordState::Skipped);

        self::assertSame(['pending' => 1, 'delivered' => 2, 'skipped' => 4, 'dead' => 3], $this->housekeeping->counts());
    }

    public function testADeadRecordIsTriedAgainAtOnce(): void
    {
        [$dead] = $this->records(1, RecordState::Dead);
        [$pending] = $this->records(1, RecordState::Pending);

        self::assertTrue($this->housekeeping->retry($dead));
        self::assertFalse($this->housekeeping->retry($pending));

        $record = $this->store->find($dead);
        self::assertSame(RecordState::Pending, $record?->state);
        self::assertSame('2026-11-17 12:00:00', $record->availableAt->format('Y-m-d H:i:s'));
    }

    public function testTheOldestDeadRecordsAreTriedAgainUpToTheLimit(): void
    {
        $dead = $this->records(3, RecordState::Dead);

        self::assertSame(2, $this->housekeeping->retryAll(2));
        self::assertSame(RecordState::Pending, $this->store->find($dead[0])?->state);
        self::assertSame(RecordState::Pending, $this->store->find($dead[1])?->state);
        self::assertSame(RecordState::Dead, $this->store->find($dead[2])?->state);
        self::assertSame(1, $this->housekeeping->retryAll());
        self::assertSame(0, $this->housekeeping->retryAll());
    }

    public function testADeadRecordIsDiscarded(): void
    {
        [$dead] = $this->records(1, RecordState::Dead);
        [$pending] = $this->records(1, RecordState::Pending);

        self::assertTrue($this->housekeeping->discard($dead));
        self::assertFalse($this->housekeeping->discard($pending));
        self::assertNull($this->store->find($dead));
    }

    public function testTheOldestDeadRecordsAreDiscardedUpToTheLimit(): void
    {
        $dead = $this->records(3, RecordState::Dead);

        self::assertSame(2, $this->housekeeping->discardAll(2));
        self::assertNull($this->store->find($dead[1]));
        self::assertNotNull($this->store->find($dead[2]));
        self::assertSame(1, $this->housekeeping->discardAll());
        self::assertSame(0, $this->housekeeping->discardAll());
    }

    public function testTheDeliveredRecordsArePurgedAfterTheirRetention(): void
    {
        $this->records(1, RecordState::Delivered);
        $this->records(1, RecordState::Skipped);
        $this->records(1, RecordState::Dead);

        self::assertSame(2, $this->housekeeping->purge(0, 5));
        self::assertSame(1, $this->housekeeping->counts()['dead']);
        self::assertSame(0, $this->housekeeping->purge());

        self::assertSame('2026-11-17 12:00:00', $this->store->purges[0][0]->format('Y-m-d H:i:s'));
        self::assertSame(5, $this->store->purges[0][1]);
        self::assertSame('2026-11-10 12:00:00', $this->store->purges[1][0]->format('Y-m-d H:i:s'));
        self::assertSame(1000, $this->store->purges[1][1]);
    }

    public function testTheDeadRecordsAreListedFiftyAtATimeFromTheFirstByDefault(): void
    {
        $this->records(51, RecordState::Dead);

        self::assertCount(50, $this->housekeeping->dead());
        self::assertSame([RecordState::Dead, 50, 0], $this->store->lists[0]);
    }

    public function testAHundredDeadRecordsAreTriedAgainOrDiscardedByDefault(): void
    {
        $this->records(101, RecordState::Dead);

        self::assertSame(100, $this->housekeeping->retryAll());
        self::assertSame(1, $this->housekeeping->counts()['dead']);

        $this->records(100, RecordState::Dead);

        self::assertSame(100, $this->housekeeping->discardAll());
        self::assertSame(1, $this->housekeeping->counts()['dead']);
    }

    public function testANegativeRetentionIsRefused(): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains('A retention lasts 0 seconds or more, not -1.');

        $this->housekeeping->purge(-1);
    }

    /**
     * @return list<RecordId>
     */
    private function records(int $count, RecordState $state): array
    {
        $ids = [];
        $at = new DateTimeImmutable('2026-11-17 10:00:00', new DateTimeZone('UTC'));

        for ($index = 0; $index < $count; ++$index) {
            $id = RecordId::generate();
            $this->store->add(new OutboxRecord($id, 'a.b', '{}', null, null, $at, $at, $state, 1, null, RecordState::Pending === $state ? null : $at));
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param list<OutboxRecord> $records
     *
     * @return list<string>
     */
    private function ids(array $records): array
    {
        return array_map(static fn (OutboxRecord $record): string => $record->id->value, $records);
    }
}
