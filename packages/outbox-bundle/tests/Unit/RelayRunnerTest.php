<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit;

use Phil\Outbox\Exception\InvalidSetting;
use Phil\Outbox\RelayReport;
use Phil\OutboxBundle\Infrastructure\Worker\RelayRunner;
use Phil\OutboxBundle\Tests\Unit\Fixture\Outboxes;
use Phil\OutboxBundle\Tests\Unit\Fixture\RecordingLockStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;

#[CoversClass(RelayRunner::class)]
final class RelayRunnerTest extends TestCase
{
    public function testARunEndsAtTheFirstEmptyBatchWhenAsked(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(5);

        $batches = [];

        $report = new RelayRunner($outboxes->relay, $outboxes->clock)->run(2, stopWhenEmpty: true, onBatch: static function (RelayReport $batch) use (&$batches): void {
            $batches[] = $batch->claimed;
        });

        self::assertEquals(new RelayReport(claimed: 5, delivered: 5), $report);
        self::assertSame([2, 2, 1, 0], $batches);
        self::assertCount(5, $outboxes->received);
        self::assertSame('2026-11-17 10:00:00', $outboxes->clock->now()->format('Y-m-d H:i:s'), 'A run that stops when empty never sleeps.');
    }

    public function testARunEndsAfterItsBatches(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(5);

        self::assertEquals(new RelayReport(claimed: 2, delivered: 2), new RelayRunner($outboxes->relay, $outboxes->clock)->run(1, 2));
        self::assertEquals(new RelayReport(claimed: 3, delivered: 3), new RelayRunner($outboxes->relay, $outboxes->clock)->run(3, 1));
    }

    public function testARunEndsWhenTheStopCallbackSaysSo(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(5);

        $asked = 0;

        $report = new RelayRunner($outboxes->relay, $outboxes->clock)->run(1, stop: static function () use (&$asked): bool {
            return 3 === ++$asked;
        });

        self::assertSame(3, $report?->delivered);
    }

    public function testARunSleepsBetweenEmptyBatchesUntilItsTimeIsSpent(): void
    {
        $outboxes = new Outboxes();
        $batches = 0;

        $report = new RelayRunner($outboxes->relay, $outboxes->clock)->run(10, timeLimit: 5, sleep: 2.0, onBatch: static function () use (&$batches): void {
            ++$batches;
        });

        self::assertTrue($report?->isEmpty());
        self::assertSame(3, $batches);
        self::assertSame('2026-11-17 10:00:06', $outboxes->clock->now()->format('Y-m-d H:i:s'));
    }

    public function testABusyRunEndsWhenItsTimeIsSpentWithoutSleeping(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(3);

        $clock = $outboxes->clock;

        $report = new RelayRunner($outboxes->relay, $clock)->run(1, timeLimit: 2, onBatch: static function () use ($clock): void {
            $clock->sleep(1);
        });

        self::assertSame(2, $report?->delivered);
    }

    public function testARunWithoutATimeLimitIsNotCutShort(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(3);

        $clock = $outboxes->clock;
        $asked = 0;

        $report = new RelayRunner($outboxes->relay, $clock)->run(1, stop: static function () use (&$asked): bool {
            return 3 === ++$asked;
        }, onBatch: static function () use ($clock): void {
            $clock->sleep(2);
        });

        self::assertSame(3, $report?->delivered);
    }

    public function testARunSleepsASecondBetweenEmptyBatchesByDefault(): void
    {
        $outboxes = new Outboxes();
        $asked = 0;

        new RelayRunner($outboxes->relay, $outboxes->clock)->run(5, stop: static function () use (&$asked): bool {
            return 2 === ++$asked;
        });

        self::assertSame('2026-11-17 10:00:01', $outboxes->clock->now()->format('Y-m-d H:i:s'));
    }

    public function testOneRunAtATimeHoldsTheLock(): void
    {
        $outboxes = new Outboxes(lease: 45);
        $outboxes->record(2);

        $store = new RecordingLockStore();
        $locks = new LockFactory($store);
        $held = $locks->createLock(RelayRunner::LOCK);
        $held->acquire();

        $runner = new RelayRunner($outboxes->relay, $outboxes->clock, $locks, 45);

        self::assertNull($runner->run(10, stopWhenEmpty: true));
        self::assertSame([], $outboxes->received);

        $held->release();
        self::assertEquals(new RelayReport(claimed: 2, delivered: 2), $runner->run(10, stopWhenEmpty: true));
        self::assertSame([90.0, 90.0], \array_slice($store->ttls, -2), 'The lock lives twice the lease, refreshed after each batch.');
        self::assertTrue($locks->createLock(RelayRunner::LOCK)->acquire(), 'The run releases the lock.');
    }

    public function testTheLockIsReleasedWhenARunFails(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(1);

        $locks = new LockFactory(new RecordingLockStore());

        try {
            new RelayRunner($outboxes->relay, $outboxes->clock, $locks)->run(5000);
            self::fail('A batch out of bounds runs.');
        } catch (InvalidSetting) {
        }

        self::assertTrue($locks->createLock(RelayRunner::LOCK)->acquire());
    }
}
