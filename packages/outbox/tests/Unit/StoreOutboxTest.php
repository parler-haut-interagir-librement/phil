<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Outbox\Codecs;
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Exception\UnknownEventType;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\RecordState;
use Phil\Outbox\StoreOutbox;
use Phil\Outbox\SystemClock;
use Phil\Outbox\Tests\Unit\Fixture\CompletionCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(StoreOutbox::class)]
#[CoversClass(SystemClock::class)]
final class StoreOutboxTest extends TestCase
{
    public function testARecordIsAddedPendingAndDatedByTheClock(): void
    {
        $store = new InMemoryOutboxStore();
        $outbox = new StoreOutbox($store, new Codecs([new CompletionCodec()]), new MockClock('2026-11-17 10:00:00', 'UTC'));

        $id = $outbox->record('set.completed', ['set' => 's1', 'holder' => 7], 'set:s1', 'completion:s1:7');

        $record = $store->find($id);
        self::assertNotNull($record);
        self::assertSame('set.completed', $record->type);
        self::assertSame(['set' => 's1', 'holder' => 7], $record->payload()->values);
        self::assertSame('set:s1', $record->orderingKey);
        self::assertSame('completion:s1:7', $record->idempotencyKey);
        self::assertSame('2026-11-17 10:00:00', $record->occurredAt->format('Y-m-d H:i:s'));
        self::assertSame(RecordState::Pending, $record->state);
        self::assertSame($id->value, $outbox->record('set.completed', ['set' => 's1', 'holder' => 7], null, 'completion:s1:7')->value);
        self::assertSame(1, $store->count(RecordState::Pending));
        self::assertNotSame($id->value, $outbox->record('set.completed')->value);
    }

    public function testATypeOutOfTheAllowlistIsRefused(): void
    {
        $store = new InMemoryOutboxStore();

        try {
            new StoreOutbox($store, new Codecs([new CompletionCodec()]))->record('set.forgotten', ['set' => 's1']);
            self::fail('A type without a codec is recorded.');
        } catch (UnknownEventType) {
            self::assertSame(0, $store->count(RecordState::Pending));
        }
    }

    public function testAPayloadOutOfBoundsIsRefused(): void
    {
        $this->expectException(InvalidRecord::class);

        new StoreOutbox(new InMemoryOutboxStore(), new Codecs([new CompletionCodec()]))->record('set.completed', ['amount' => 1.5]);
    }

    public function testTheSystemClockTellsTheTimeInUtc(): void
    {
        $before = new DateTimeImmutable();
        $now = new SystemClock()->now();

        self::assertEquals(new DateTimeZone('UTC'), $now->getTimezone());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        self::assertLessThanOrEqual(new DateTimeImmutable()->getTimestamp(), $now->getTimestamp());
    }
}
