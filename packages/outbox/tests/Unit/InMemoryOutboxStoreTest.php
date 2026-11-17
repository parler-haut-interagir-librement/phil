<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use Override;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use Phil\Outbox\Test\OutboxStoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryOutboxStore::class)]
#[CoversClass(OutboxStoreTestCase::class)]
final class InMemoryOutboxStoreTest extends OutboxStoreTestCase
{
    public function testAResetForgetsEveryRecordAndLease(): void
    {
        $store = new InMemoryOutboxStore();
        $record = OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of([]), 'k', null, self::at('10:00:00'));
        $other = OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of([]), null, null, self::at('10:00:00'));
        $store->add($record);
        $store->add($other);
        $store->claim('c', 1, self::at('10:00:00'), self::at('10:01:00'));

        $store->reset();

        self::assertSame(0, $store->count(RecordState::Pending));
        self::assertNull($store->find($other->id));
        $store->add($record);
        self::assertSame([$record->id->value], self::ids($store->claim('d', 1, self::at('10:00:00'), self::at('10:01:00'))));
        self::assertFalse($store->markDelivered($record->id, 'c', self::at('10:00:01')));
    }

    public function testANegativeOffsetListsFromTheFirstRecordAndANegativeLimitListsNone(): void
    {
        $store = new InMemoryOutboxStore();
        $record = OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of([]), null, null, self::at('10:00:00'));
        $store->add($record);
        $store->add(OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of([]), null, null, self::at('10:00:00')));

        self::assertSame([$record->id->value], self::ids($store->list(RecordState::Pending, 1, -3)));
        self::assertSame([], $store->list(RecordState::Pending, -3));
    }

    public function testADeliveredRecordWithoutASettlementIsNotPurged(): void
    {
        $store = new InMemoryOutboxStore();
        $at = self::at('10:00:00');
        $record = new OutboxRecord(RecordId::generate(), 'a.b', '{}', null, null, $at, $at, RecordState::Delivered);
        $store->add($record);

        self::assertSame(0, $store->purgeDelivered(self::at('11:00:00'), 10));
        self::assertNotNull($store->find($record->id));
    }

    #[Override]
    protected function createStore(): OutboxStore
    {
        return new InMemoryOutboxStore();
    }
}
