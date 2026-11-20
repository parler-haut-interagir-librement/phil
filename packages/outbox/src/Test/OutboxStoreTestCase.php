<?php

declare(strict_types=1);

namespace Phil\Outbox\Test;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use PHPUnit\Framework\TestCase;

/**
 * The contract every store passes: an adapter's test extends this case and gives `createStore()` an empty store. The
 * moments are whole seconds in UTC, as a store keeps them.
 */
abstract class OutboxStoreTestCase extends TestCase
{
    private const string CLAIM = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string OTHER_CLAIM = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    /**
     * An empty store.
     */
    abstract protected function createStore(): OutboxStore;

    public function testARecordIsKeptAsItIsAdded(): void
    {
        $store = $this->createStore();
        $record = OutboxRecord::pending(RecordId::generate(), 'sets.set_completed', Payload::of(['set' => 'é-42', 'holder' => 7, 'first' => true, 'note' => null]), 'set:42', 'completion:42', self::at('10:00:00'));

        self::assertTrue($record->id->equals($store->add($record)));

        $kept = $store->find($record->id);
        self::assertNotNull($kept);
        self::assertSame($record->id->value, $kept->id->value);
        self::assertSame('sets.set_completed', $kept->type);
        self::assertSame(['set' => 'é-42', 'holder' => 7, 'first' => true, 'note' => null], $kept->payload()->values);
        self::assertSame('set:42', $kept->orderingKey);
        self::assertSame('completion:42', $kept->idempotencyKey);
        self::assertSame(self::at('10:00:00')->getTimestamp(), $kept->occurredAt->getTimestamp());
        self::assertSame(self::at('10:00:00')->getTimestamp(), $kept->availableAt->getTimestamp());
        self::assertSame(RecordState::Pending, $kept->state);
        self::assertSame(0, $kept->attempts);
        self::assertNull($kept->lastError);
        self::assertNull($kept->settledAt);
        self::assertNull($store->find(RecordId::generate()));
    }

    public function testARecordWithoutKeysIsKept(): void
    {
        $store = $this->createStore();
        $record = $this->add($store, 'a.b');

        $kept = $store->find($record->id);
        self::assertNotNull($kept);
        self::assertNull($kept->orderingKey);
        self::assertNull($kept->idempotencyKey);
        self::assertSame([], $kept->payload()->values);
    }

    public function testAnIdempotencyKeyRecordsAnEventOnceForItsType(): void
    {
        $store = $this->createStore();
        $first = $this->add($store, 'a.b', idempotencyKey: 'k1');
        $again = OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of(['n' => 2]), null, 'k1', self::at('10:00:01'));

        self::assertSame($first->id->value, $store->add($again)->value);
        self::assertNull($store->find($again->id));
        self::assertSame(1, $store->count(RecordState::Pending));

        $otherType = $this->add($store, 'a.c', idempotencyKey: 'k1');
        $otherKey = $this->add($store, 'a.b', idempotencyKey: 'k2');
        self::assertNotNull($store->find($otherType->id));
        self::assertNotNull($store->find($otherKey->id));
        self::assertSame(3, $store->count(RecordState::Pending));
    }

    public function testRecordsWithoutAnIdempotencyKeyAreAllKept(): void
    {
        $store = $this->createStore();
        $this->add($store, 'a.b');
        $this->add($store, 'a.b');

        self::assertSame(2, $store->count(RecordState::Pending));
    }

    public function testAClaimLeasesTheAvailableRecordsInTheOrderTheyWereAdded(): void
    {
        $store = $this->createStore();
        $first = $this->add($store, 'a.b', at: '10:00:02');
        $second = $this->add($store, 'a.b', at: '10:00:01');
        $later = $this->add($store, 'a.b', at: '10:00:30');
        $third = $this->add($store, 'a.b', at: '10:00:03');

        $claimed = $store->claim(self::CLAIM, 10, self::at('10:00:10'), self::at('10:01:10'));

        self::assertSame([$first->id->value, $second->id->value, $third->id->value], self::ids($claimed));
        self::assertSame([], $store->claim(self::OTHER_CLAIM, 10, self::at('10:00:10'), self::at('10:01:10')));
        self::assertSame([$later->id->value], self::ids($store->claim(self::OTHER_CLAIM, 10, self::at('10:00:30'), self::at('10:01:30'))));
    }

    public function testAClaimTakesAtMostItsLimit(): void
    {
        $store = $this->createStore();
        $first = $this->add($store, 'a.b');
        $second = $this->add($store, 'a.b');
        $third = $this->add($store, 'a.b');

        self::assertSame([], $store->claim(self::CLAIM, 0, self::at('10:00:10'), self::at('10:01:10')));
        self::assertSame([$first->id->value, $second->id->value], self::ids($store->claim(self::CLAIM, 2, self::at('10:00:10'), self::at('10:01:10'))));
        self::assertSame([$third->id->value], self::ids($store->claim(self::OTHER_CLAIM, 2, self::at('10:00:10'), self::at('10:01:10'))));
    }

    public function testALeaseThatRanOutLetsAnotherClaimTakeTheRecord(): void
    {
        $store = $this->createStore();
        $record = $this->add($store, 'a.b');
        $store->claim(self::CLAIM, 10, self::at('10:00:10'), self::at('10:01:10'));

        self::assertSame([], $store->claim(self::OTHER_CLAIM, 10, self::at('10:01:09'), self::at('10:02:09')));
        self::assertSame([$record->id->value], self::ids($store->claim(self::OTHER_CLAIM, 10, self::at('10:01:10'), self::at('10:02:10'))));
        self::assertFalse($store->markDelivered($record->id, self::CLAIM, self::at('10:01:11')));
        self::assertTrue($store->markDelivered($record->id, self::OTHER_CLAIM, self::at('10:01:11')));
    }

    public function testADeliveredRecordIsSettledAndNeverClaimedAgain(): void
    {
        $store = $this->createStore();
        $record = $this->add($store, 'a.b');
        $store->claim(self::CLAIM, 10, self::at('10:00:10'), self::at('10:01:10'));

        self::assertFalse($store->markDelivered(RecordId::generate(), self::CLAIM, self::at('10:00:11')));
        self::assertTrue($store->markDelivered($record->id, self::CLAIM, self::at('10:00:11')));
        self::assertFalse($store->markDelivered($record->id, self::CLAIM, self::at('10:00:12')));

        $kept = $store->find($record->id);
        self::assertSame(RecordState::Delivered, $kept?->state);
        self::assertSame(self::at('10:00:11')->getTimestamp(), $kept->settledAt?->getTimestamp());
        self::assertSame([], $store->claim(self::OTHER_CLAIM, 10, self::at('11:00:00'), self::at('11:01:00')));
        self::assertSame(0, $store->count(RecordState::Pending));
        self::assertSame(1, $store->count(RecordState::Delivered));
    }

    public function testASkippedRecordIsSettledAndLetsItsOrderingKeyGo(): void
    {
        $store = $this->createStore();
        $skipped = $this->add($store, 'a.b', 'k1');
        $later = $this->add($store, 'a.b', 'k1');

        self::assertSame([$skipped->id->value], self::ids($store->claim(self::CLAIM, 10, self::at('10:00:10'), self::at('10:01:10'))));
        self::assertFalse($store->markSkipped($skipped->id, self::OTHER_CLAIM, self::at('10:00:11')));
        self::assertTrue($store->markSkipped($skipped->id, self::CLAIM, self::at('10:00:11')));
        self::assertFalse($store->markSkipped($skipped->id, self::CLAIM, self::at('10:00:12')));

        $kept = $store->find($skipped->id);
        self::assertSame(RecordState::Skipped, $kept?->state);
        self::assertSame(0, $kept->attempts);
        self::assertNull($kept->lastError);
        self::assertSame(self::at('10:00:11')->getTimestamp(), $kept->settledAt?->getTimestamp());
        self::assertSame(1, $store->count(RecordState::Skipped));
        self::assertSame([$skipped->id->value], self::ids($store->list(RecordState::Skipped, 10)));
        self::assertSame([$later->id->value], self::ids($store->claim(self::OTHER_CLAIM, 10, self::at('10:00:20'), self::at('10:01:20'))));
        self::assertFalse($store->revive($skipped->id, self::at('10:00:30')));
        self::assertFalse($store->discard($skipped->id));
    }

    public function testAReleasedRecordWaitsForItsNextAttempt(): void
    {
        $store = $this->createStore();
        $record = $this->add($store, 'a.b');
        $store->claim(self::CLAIM, 10, self::at('10:00:10'), self::at('10:01:10'));

        self::assertFalse($store->release($record->id, self::OTHER_CLAIM, 1, 'E: other', self::at('10:01:00')));
        self::assertTrue($store->release($record->id, self::CLAIM, 1, 'RuntimeException: down', self::at('10:01:00')));
        self::assertFalse($store->release($record->id, self::CLAIM, 2, 'E: again', self::at('10:02:00')));

        $kept = $store->find($record->id);
        self::assertSame(RecordState::Pending, $kept?->state);
        self::assertSame(1, $kept->attempts);
        self::assertSame('RuntimeException: down', $kept->lastError);
        self::assertSame(self::at('10:01:00')->getTimestamp(), $kept->availableAt->getTimestamp());
        self::assertNull($kept->settledAt);
        self::assertSame([], $store->claim(self::CLAIM, 10, self::at('10:00:59'), self::at('10:01:59')));
        self::assertSame([$record->id->value], self::ids($store->claim(self::OTHER_CLAIM, 10, self::at('10:01:00'), self::at('10:02:00'))));
    }

    public function testADeadRecordIsKeptToBeInspected(): void
    {
        $store = $this->createStore();
        $record = $this->add($store, 'a.b');
        $store->claim(self::CLAIM, 10, self::at('10:00:10'), self::at('10:01:10'));

        self::assertFalse($store->markDead($record->id, self::OTHER_CLAIM, 3, 'E: other', self::at('10:00:11')));
        self::assertTrue($store->markDead($record->id, self::CLAIM, 3, 'LogicException: gone', self::at('10:00:11')));
        self::assertFalse($store->markDead($record->id, self::CLAIM, 4, 'E: again', self::at('10:00:12')));

        $kept = $store->find($record->id);
        self::assertSame(RecordState::Dead, $kept?->state);
        self::assertSame(3, $kept->attempts);
        self::assertSame('LogicException: gone', $kept->lastError);
        self::assertSame(self::at('10:00:11')->getTimestamp(), $kept->settledAt?->getTimestamp());
        self::assertSame([], $store->claim(self::OTHER_CLAIM, 10, self::at('11:00:00'), self::at('11:01:00')));
        self::assertSame([$record->id->value], self::ids($store->list(RecordState::Dead, 10)));
        self::assertSame(1, $store->count(RecordState::Dead));
    }

    public function testARevivedRecordIsPendingAgainWithItsLastError(): void
    {
        $store = $this->createStore();
        $record = $this->add($store, 'a.b');
        $pending = $this->add($store, 'a.b');
        $store->claim(self::CLAIM, 1, self::at('10:00:10'), self::at('10:01:10'));
        $store->markDead($record->id, self::CLAIM, 3, 'E: gone', self::at('10:00:11'));

        self::assertFalse($store->revive($pending->id, self::at('10:05:00')));
        self::assertFalse($store->revive(RecordId::generate(), self::at('10:05:00')));
        self::assertTrue($store->revive($record->id, self::at('10:05:00')));
        self::assertFalse($store->revive($record->id, self::at('10:05:00')));

        $kept = $store->find($record->id);
        self::assertSame(RecordState::Pending, $kept?->state);
        self::assertSame(0, $kept->attempts);
        self::assertSame('E: gone', $kept->lastError);
        self::assertSame(self::at('10:05:00')->getTimestamp(), $kept->availableAt->getTimestamp());
        self::assertNull($kept->settledAt);
        self::assertSame([$record->id->value], self::ids($store->claim(self::OTHER_CLAIM, 1, self::at('10:05:00'), self::at('10:06:00'))));
    }

    public function testOnlyADeadRecordIsDiscarded(): void
    {
        $store = $this->createStore();
        $record = $this->add($store, 'a.b');
        $pending = $this->add($store, 'a.b');
        $store->claim(self::CLAIM, 1, self::at('10:00:10'), self::at('10:01:10'));
        $store->markDead($record->id, self::CLAIM, 3, 'E: gone', self::at('10:00:11'));

        self::assertFalse($store->discard($pending->id));
        self::assertFalse($store->discard(RecordId::generate()));
        self::assertTrue($store->discard($record->id));
        self::assertFalse($store->discard($record->id));
        self::assertNull($store->find($record->id));
        self::assertNotNull($store->find($pending->id));
    }

    public function testTheRecordsOfAnOrderingKeyGoOneAfterTheOther(): void
    {
        $store = $this->createStore();
        $first = $this->add($store, 'a.b', 'set:1');
        $other = $this->add($store, 'a.b', 'set:2');
        $second = $this->add($store, 'a.b', 'set:1');
        $free = $this->add($store, 'a.b');
        $third = $this->add($store, 'a.b', 'set:1');

        self::assertSame([$first->id->value, $other->id->value, $free->id->value], self::ids($store->claim(self::CLAIM, 10, self::at('10:00:10'), self::at('10:01:10'))));
        self::assertSame([], $store->claim(self::OTHER_CLAIM, 10, self::at('10:00:30'), self::at('10:01:30')), 'A leased record holds back its key.');

        $store->markDelivered($other->id, self::CLAIM, self::at('10:00:11'));
        $store->markDelivered($free->id, self::CLAIM, self::at('10:00:11'));
        $store->markDelivered($first->id, self::CLAIM, self::at('10:00:11'));
        self::assertSame([$second->id->value], self::ids($store->claim(self::CLAIM, 10, self::at('10:00:12'), self::at('10:01:12'))));

        $store->release($second->id, self::CLAIM, 1, 'E: down', self::at('10:05:00'));
        self::assertSame([], $store->claim(self::CLAIM, 10, self::at('10:00:13'), self::at('10:01:13')), 'A record waiting for its next attempt holds back its key.');

        $claimed = $store->claim(self::CLAIM, 10, self::at('10:05:00'), self::at('10:06:00'));
        self::assertSame([$second->id->value], self::ids($claimed));
        $store->markDead($second->id, self::CLAIM, 2, 'E: gone', self::at('10:05:01'));
        self::assertSame([], $store->claim(self::CLAIM, 10, self::at('10:05:02'), self::at('10:06:02')), 'A dead record holds back its key.');

        $store->discard($second->id);
        self::assertSame([$third->id->value], self::ids($store->claim(self::CLAIM, 10, self::at('10:05:03'), self::at('10:06:03'))));
    }

    public function testTheListsComeInTheOrderTheRecordsWereAdded(): void
    {
        $store = $this->createStore();
        $records = [];

        for ($index = 0; $index < 4; ++$index) {
            $records[] = $this->add($store, 'a.b');
        }

        $pending = $this->add($store, 'a.b');
        $store->claim(self::CLAIM, 4, self::at('10:00:10'), self::at('10:01:10'));

        foreach ($records as $record) {
            $store->markDead($record->id, self::CLAIM, 1, 'E', self::at('10:00:11'));
        }

        self::assertSame(self::ids($records), self::ids($store->list(RecordState::Dead, 10)));
        self::assertSame(self::ids(\array_slice($records, 1, 2)), self::ids($store->list(RecordState::Dead, 2, 1)));
        self::assertSame([], $store->list(RecordState::Dead, 0));
        self::assertSame([$pending->id->value], self::ids($store->list(RecordState::Pending, 10)));
        self::assertSame([], $store->list(RecordState::Delivered, 10));
        self::assertSame(4, $store->count(RecordState::Dead));
        self::assertSame(1, $store->count(RecordState::Pending));
        self::assertSame(0, $store->count(RecordState::Delivered));
    }

    public function testThePurgeRemovesTheDeliveredRecordsPastTheirRetention(): void
    {
        $store = $this->createStore();
        $old = $this->add($store, 'a.b');
        $older = $this->add($store, 'a.b');
        $recent = $this->add($store, 'a.b');
        $dead = $this->add($store, 'a.b');
        $skipped = $this->add($store, 'a.b');
        $pending = $this->add($store, 'a.b');
        $store->claim(self::CLAIM, 5, self::at('10:00:10'), self::at('10:01:10'));
        $store->markSkipped($skipped->id, self::CLAIM, self::at('10:00:12'));
        $store->markDelivered($old->id, self::CLAIM, self::at('10:00:20'));
        $store->markDelivered($older->id, self::CLAIM, self::at('10:00:11'));
        $store->markDelivered($recent->id, self::CLAIM, self::at('10:00:30'));
        $store->markDead($dead->id, self::CLAIM, 1, 'E', self::at('10:00:11'));

        self::assertSame(0, $store->purgeDelivered(self::at('10:00:30'), 0));
        self::assertSame(1, $store->purgeDelivered(self::at('10:00:30'), 1));
        self::assertNull($store->find($old->id), 'The purge goes in the order the records were added.');
        self::assertSame(2, $store->purgeDelivered(self::at('10:00:30'), 10));
        self::assertNull($store->find($older->id));
        self::assertNull($store->find($skipped->id), 'A skipped record is purged like a delivered one.');
        self::assertNotNull($store->find($recent->id), 'A record settled at the cutoff is kept.');
        self::assertNotNull($store->find($dead->id));
        self::assertNotNull($store->find($pending->id));
        self::assertSame(0, $store->purgeDelivered(self::at('10:00:30'), 10));
    }

    /**
     * A moment of the day the tests run on, in UTC.
     */
    protected static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-17 '.$time, new DateTimeZone('UTC'));
    }

    /**
     * @param list<OutboxRecord> $records
     *
     * @return list<string>
     */
    protected static function ids(array $records): array
    {
        return array_map(static fn (OutboxRecord $record): string => $record->id->value, $records);
    }

    private function add(OutboxStore $store, string $type, ?string $orderingKey = null, ?string $idempotencyKey = null, string $at = '10:00:00'): OutboxRecord
    {
        $record = OutboxRecord::pending(RecordId::generate(), $type, Payload::of([]), $orderingKey, $idempotencyKey, self::at($at));
        $store->add($record);

        return $record;
    }
}
