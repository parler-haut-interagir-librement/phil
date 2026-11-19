<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\Outbox\Backoff;
use Phil\Outbox\Codecs;
use Phil\Outbox\Exception\InvalidSetting;
use Phil\Outbox\Exception\UndeliverableRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use Phil\Outbox\Relay;
use Phil\Outbox\RelayReport;
use Phil\Outbox\StoreOutbox;
use Phil\Outbox\Tests\Unit\Fixture\CompletionCodec;
use Phil\Outbox\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\Outbox\Tests\Unit\Fixture\SetCompleted;
use Phil\Outbox\Tests\Unit\Fixture\SpyStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Relay::class)]
#[CoversClass(RelayReport::class)]
#[CoversClass(UndeliverableRecord::class)]
final class RelayTest extends TestCase
{
    private SpyStore $store;

    private MockClock $clock;

    private CompletionCodec $codec;

    private RecordingDispatcher $dispatcher;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new SpyStore();
        $this->clock = new MockClock('2026-11-17 10:00:00', 'UTC');
        $this->codec = new CompletionCodec();
        $this->dispatcher = new RecordingDispatcher();
    }

    public function testTheRecordsAreDeliveredInOrderAndMarkedDelivered(): void
    {
        $first = $this->record('s1', 'set:s1');
        $second = $this->record('s2');

        $report = $this->relay()->relay();

        self::assertEquals(new RelayReport(claimed: 2, delivered: 2), $report);
        self::assertEquals([new SetCompleted($first->value, 's1', 7), new SetCompleted($second->value, 's2', 7)], $this->dispatcher->events);
        self::assertSame(RecordState::Delivered, $this->store->find($first)?->state);
        self::assertSame('2026-11-17 10:00:00', $this->store->find($first)->settledAt?->format('Y-m-d H:i:s'));
        self::assertTrue($this->relay()->relay()->isEmpty());
    }

    public function testAClaimIsLeasedForTheLeaseAndUnderATokenOfItsOwn(): void
    {
        $this->record('s1');

        $this->relay(lease: 90)->relay(25);
        $this->relay()->relay();

        [$claim, $limit, $now, $leaseUntil] = $this->store->claims[0];
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $claim);
        self::assertNotSame($claim, $this->store->claims[1][0]);
        self::assertSame(25, $limit);
        self::assertSame(100, $this->store->claims[1][1]);
        self::assertSame('2026-11-17 10:00:00', $now->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-17 10:01:30', $leaseUntil->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-17 10:01:00', $this->store->claims[1][3]->format('Y-m-d H:i:s'));
    }

    public function testAFailedDispatchIsTriedAgainAfterTheBackoff(): void
    {
        $id = $this->record('s1');
        $this->dispatcher->listener = static function (): never {
            throw new RuntimeException('The listener is down.');
        };
        $relay = $this->relay(new Backoff(10, 3600, 5));

        self::assertEquals(new RelayReport(claimed: 1, retried: 1), $relay->relay());

        $record = $this->store->find($id);
        self::assertSame(RecordState::Pending, $record?->state);
        self::assertSame(1, $record->attempts);
        self::assertSame('RuntimeException (code 0)', $record->lastError);
        self::assertSame('2026-11-17 10:00:10', $record->availableAt->format('Y-m-d H:i:s'));

        $this->clock->sleep(9);
        self::assertTrue($relay->relay()->isEmpty());

        $this->clock->sleep(1);
        self::assertEquals(new RelayReport(claimed: 1, retried: 1), $relay->relay());
        self::assertSame(2, $this->store->find($id)?->attempts);
        self::assertSame('2026-11-17 10:00:30', $this->store->find($id)->availableAt->format('Y-m-d H:i:s'));

        $this->dispatcher->listener = null;
        $this->clock->sleep(20);
        self::assertEquals(new RelayReport(claimed: 1, delivered: 1), $relay->relay());
        self::assertCount(1, $this->dispatcher->events);
    }

    public function testARecordIsDeadAfterItsLastAttempt(): void
    {
        $id = $this->record('s1');
        $this->dispatcher->listener = static function (): never {
            throw new RuntimeException('Still down.');
        };

        $this->relay()->relay();
        $this->clock->sleep(10);
        self::assertEquals(new RelayReport(claimed: 1, dead: 1), $this->relay()->relay());

        $record = $this->store->find($id);
        self::assertSame(RecordState::Dead, $record?->state);
        self::assertSame(2, $record->attempts);
        self::assertSame('RuntimeException (code 0)', $record->lastError);
        self::assertSame('2026-11-17 10:00:10', $record->settledAt?->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{Closure(self): RecordId, string}>
     */
    public static function undeliverableRecords(): iterable
    {
        yield 'a type no codec rebuilds any more' => [
            static fn (self $test): RecordId => $test->keep('set.forgotten', '{"set":"s1","holder":7}'),
            'Phil\Outbox\Exception\UnknownEventType (code 0): No codec rebuilds the event type "set.forgotten": register one before recording it.',
        ];
        yield 'a payload that is not one' => [
            static fn (self $test): RecordId => $test->keep('set.completed', '{"set":[1]}'),
            'Phil\Outbox\Exception\InvalidRecord (code 0): A payload is a JSON object of identifiers, one level deep.',
        ];
        yield 'a payload without what the codec reads' => [
            static fn (self $test): RecordId => $test->keep('set.completed', '{"set":"s1"}'),
            'Phil\Outbox\Exception\InvalidRecord (code 0): The payload has no integer "holder".',
        ];
        yield 'a record the codec declares undeliverable' => [
            static function (self $test): RecordId {
                $test->codec->before = static function (): never {
                    throw UndeliverableRecord::because('The set is gone.', new RuntimeException('previous'));
                };

                return $test->keep('set.completed', '{"set":"s1","holder":7}');
            },
            'Phil\Outbox\Exception\UndeliverableRecord (code 0): The set is gone.',
        ];
    }

    /**
     * @param Closure(self): RecordId $keep
     */
    #[DataProvider('undeliverableRecords')]
    public function testARecordThatCanNeverBecomeAnEventIsDeadAtOnce(Closure $keep, string $error): void
    {
        $id = $keep($this);

        self::assertEquals(new RelayReport(claimed: 1, dead: 1), $this->relay(new Backoff(maxAttempts: 5))->relay());

        $record = $this->store->find($id);
        self::assertSame(RecordState::Dead, $record?->state);
        self::assertSame(1, $record->attempts);
        self::assertSame($error, $record->lastError);
        self::assertSame([], $this->dispatcher->events);
    }

    public function testACodecThatFailsOtherwiseIsTriedAgain(): void
    {
        $id = $this->record('s1');
        $this->codec->before = static function (): never {
            throw new RuntimeException('The database is away.');
        };

        self::assertEquals(new RelayReport(claimed: 1, retried: 1), $this->relay()->relay());
        self::assertSame('RuntimeException (code 0)', $this->store->find($id)?->lastError);
        self::assertSame([], $this->dispatcher->events);
    }

    public function testTheRecordsLeftWhenTheLeaseRunsOutAreNotTried(): void
    {
        $this->record('s1');
        $second = $this->record('s2');
        $this->record('s3');
        $clock = $this->clock;
        $this->dispatcher->listener = static function () use ($clock): void {
            $clock->sleep(60);
        };

        self::assertEquals(new RelayReport(claimed: 3, delivered: 1, expired: 2), $this->relay()->relay());
        self::assertSame(RecordState::Pending, $this->store->find($second)?->state);
        self::assertSame(0, $this->store->find($second)->attempts);

        $this->dispatcher->listener = null;
        self::assertEquals(new RelayReport(claimed: 2, delivered: 2), $this->relay()->relay());
    }

    public function testARecordIsTriedWhileItsLeaseRuns(): void
    {
        $this->record('s1');
        $this->record('s2');
        $clock = $this->clock;
        $this->dispatcher->listener = static function () use ($clock): void {
            $clock->sleep(29);
        };

        self::assertEquals(new RelayReport(claimed: 2, delivered: 2), $this->relay()->relay());
    }

    public function testAMarkTakenOverByAnotherClaimIsLost(): void
    {
        $this->record('s1');
        $this->takeOverOnDispatch();

        self::assertEquals(new RelayReport(claimed: 1, lost: 1), $this->relay()->relay());
    }

    public function testARetryTakenOverByAnotherClaimIsLost(): void
    {
        $this->record('s1');
        $this->takeOverOnDispatch(new RuntimeException('down'));

        self::assertEquals(new RelayReport(claimed: 1, lost: 1), $this->relay(new Backoff(maxAttempts: 5))->relay());
    }

    public function testADeathTakenOverByAnotherClaimIsLost(): void
    {
        $this->record('s1');
        $this->takeOverOnDispatch(new RuntimeException('down'));

        self::assertEquals(new RelayReport(claimed: 1, lost: 1), $this->relay(new Backoff(maxAttempts: 1))->relay());
    }

    public function testABatchTakesAtMostItsLimit(): void
    {
        $this->record('s1');
        $this->record('s2');
        $this->record('s3');

        self::assertEquals(new RelayReport(claimed: 1, delivered: 1), $this->relay()->relay(1));
        self::assertEquals(new RelayReport(claimed: 2, delivered: 2), $this->relay()->relay(1000));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLimits(): iterable
    {
        yield 'none' => [0];
        yield 'too many' => [1001];
    }

    #[DataProvider('refusedLimits')]
    public function testALimitOutOfBoundsIsRefused(int $limit): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A batch holds from 1 to 1000 records, not %d.', $limit));

        $this->relay()->relay($limit);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLeases(): iterable
    {
        yield 'none' => [0];
        yield 'too long' => [3601];
    }

    #[DataProvider('refusedLeases')]
    public function testALeaseOutOfBoundsIsRefused(int $lease): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A lease lasts from 1 to 3600 seconds, not %d.', $lease));

        $this->relay(lease: $lease);
    }

    public function testTheShortestAndLongestLeasesAreAccepted(): void
    {
        $this->record('s1');

        $this->relay(lease: 1)->relay();
        $this->relay(lease: 3600)->relay();

        self::assertSame('2026-11-17 10:00:01', $this->store->claims[0][3]->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-17 11:00:00', $this->store->claims[1][3]->format('Y-m-d H:i:s'));
    }

    public function testTheReportsAddUp(): void
    {
        self::assertTrue(new RelayReport()->isEmpty());
        self::assertFalse(new RelayReport(claimed: 1)->isEmpty());
        self::assertEquals(new RelayReport(3, 5, 7, 9, 11, 13), new RelayReport(1, 2, 3, 4, 5, 6)->plus(new RelayReport(2, 3, 4, 5, 6, 7)));
    }

    /**
     * Keeps a record as a store holds it, past the checks of a new record.
     */
    public function keep(string $type, string $payload): RecordId
    {
        $id = RecordId::generate();
        $at = new DateTimeImmutable('2026-11-17 10:00:00 UTC');
        $this->store->add(new OutboxRecord($id, $type, $payload, null, null, $at, $at));

        return $id;
    }

    private function record(string $set, ?string $orderingKey = null): RecordId
    {
        return new StoreOutbox($this->store, new Codecs([$this->codec]), $this->clock)->record('set.completed', ['set' => $set, 'holder' => 7], $orderingKey);
    }

    private function relay(Backoff $backoff = new Backoff(10, 3600, 2), int $lease = 60): Relay
    {
        return new Relay($this->store, new Codecs([$this->codec]), $this->dispatcher, $this->clock, $backoff, $lease);
    }

    private function takeOverOnDispatch(?RuntimeException $failure = null): void
    {
        $store = $this->store;
        $clock = $this->clock;
        $this->dispatcher->listener = static function () use ($store, $clock, $failure): void {
            $clock->sleep(60);
            $store->claim('cccccccccccccccccccccccccccccccc', 10, $clock->now(), $clock->now()->modify('+60 seconds'));

            if ($failure instanceof RuntimeException) {
                throw $failure;
            }
        };
    }
}
