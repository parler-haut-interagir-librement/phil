<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Application\RetentionRun;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ExpiringLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedScheduleSource;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\InterruptingModule;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingPurger;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ScriptedLockStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(RetentionJob::class)]
#[CoversClass(RetentionRun::class)]
#[CoversClass(HeldLock::class)]
final class RetentionJobTest extends TestCase
{
    public function testARunPurgesAtThePresentInstantAndLogsCounts(): void
    {
        $purger = new RecordingPurger('trash', purged: 3);
        $log = new TestHandler();
        $job = new RetentionJob($this->retentionRun([$purger]), new LockFactory(new InMemoryStore()), new MockClock('2026-03-31T00:00:00Z'), new Logger('privacy', [$log]));

        self::assertSame(['R-1' => 3], $job->run());
        self::assertSame('2026-03-01', $purger->orders[0]->cutoff->format('Y-m-d'));
        self::assertSame(['rule' => 'R-1', 'count' => 3], $log->getRecords()[0]->context);
        self::assertSame('Retention rule {rule}: {count} record(s) purged.', $log->getRecords()[0]->message);
    }

    public function testThePlanSaysWhatARunWouldPurgeAndPurgesNothing(): void
    {
        $purger = new RecordingPurger('trash');
        $job = new RetentionJob($this->retentionRun([$purger]), new LockFactory(new InMemoryStore()), new MockClock('2026-03-31T00:00:00Z'));

        self::assertSame('R-1', $job->plan()[0]->ruleId);
        self::assertSame('2026-03-01', $job->plan()[0]->cutoff->format('Y-m-d'));
        self::assertSame([], $purger->orders);
    }

    public function testThePlanMeetsTheSameRefusalAsARun(): void
    {
        $this->expectException(MisconfiguredPrivacy::class);

        new RetentionJob($this->retentionRun([]), new LockFactory(new InMemoryStore()), new MockClock())->plan();
    }

    public function testJobsWithoutACutoffAreNamed(): void
    {
        $job = new RetentionJob($this->retentionRun([new RecordingPurger('trash')]), new LockFactory(new InMemoryStore()), new MockClock());

        self::assertSame(['R-2'], array_map(static fn (RetentionRule $rule): string => $rule->id, $job->jobsWithoutCutoff()));
    }

    public function testOneRunAtATime(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $held = $locks->createLock(RetentionJob::LOCK);
        $held->acquire();

        $purger = new RecordingPurger('trash');
        $job = new RetentionJob($this->retentionRun([$purger]), $locks, new MockClock());

        self::assertNull($job->run());
        self::assertSame([], $purger->orders);
        $held->release();
        self::assertNotNull($job->run());
        self::assertTrue($locks->createLock(RetentionJob::LOCK)->acquire());
    }

    public function testARunThatCannotTakeTheLockPurgesNothing(): void
    {
        $locks = new ScriptedLockStore();
        $locks->grants = false;

        $purger = new RecordingPurger('trash');

        self::assertNull(new RetentionJob($this->retentionRun([$purger]), new LockFactory($locks), new MockClock())->run());
        self::assertSame([], $purger->orders);
    }

    public function testTheLockIsReleasedWhenAPurgerFails(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $job = new RetentionJob($this->retentionRun([]), $locks, new MockClock());

        try {
            $job->run();
            self::fail('A job line without a purger must not pass.');
        } catch (MisconfiguredPrivacy) {
            self::assertTrue($locks->createLock(RetentionJob::LOCK)->acquire());
        }
    }

    public function testTheLockLastsAnHourAndIsRenewedBeforeEachPurgeOrder(): void
    {
        $store = new RecordingLockStore();
        new RetentionJob($this->retentionRun([new RecordingPurger('trash')]), new LockFactory($store), new MockClock())->run();

        self::assertSame([3600.0, 3600.0], $store->lifetimes);
    }

    public function testARunLongerThanTheLockLastsKeepsItByRenewingItBeforeEachPurgeOrder(): void
    {
        $clock = new MockClock('2026-03-31T00:00:00Z');
        $locks = new LockFactory(new ExpiringLockStore($clock));
        $other = static fn (): bool => $locks->createLock(RetentionJob::LOCK, RetentionJob::LOCK_TTL, false)->acquire();
        $first = new InterruptingModule('trash', static function () use ($clock, $other): void {
            $clock->sleep(RetentionJob::LOCK_TTL - 1);
            self::assertFalse($other(), 'The lock is held during the first purge order.');
        });
        $second = new InterruptingModule('logs', static function () use ($clock, $other): void {
            $clock->sleep(RetentionJob::LOCK_TTL - 1);
            self::assertFalse($other(), 'Renewed before the second purge order, the lock outlives the first hour.');
        });

        self::assertSame(['R-1' => 1, 'R-3' => 1], new RetentionJob($this->retentionRun([$first, $second], withLogs: true), $locks, $clock)->run());
        self::assertTrue($other(), 'The lock is released at the end.');
    }

    public function testARunWhoseLockWasTakenOverStopsBeforeItsNextPurgeOrder(): void
    {
        $clock = new MockClock('2026-03-31T00:00:00Z');
        $locks = new LockFactory(new ExpiringLockStore($clock));
        $taker = $locks->createLock(RetentionJob::LOCK, RetentionJob::LOCK_TTL, false);
        $first = new InterruptingModule('trash', static function () use ($clock, $taker): void {
            $clock->sleep(RetentionJob::LOCK_TTL + 1);
            self::assertTrue($taker->acquire(), 'Expired, the lock goes to another run.');
        });
        $second = new InterruptingModule('logs', static function (): void {
        });
        $log = new TestHandler();

        self::assertNull(new RetentionJob($this->retentionRun([$first, $second], withLogs: true), $locks, $clock, new Logger('privacy', [$log]))->run());
        self::assertSame(1, $first->calls);
        self::assertSame(0, $second->calls, 'The purge order after the loss is left to the run that holds the lock.');
        self::assertTrue($taker->isAcquired(), 'The lock of the other run is left alone.');
        self::assertCount(1, $log->getRecords());
        self::assertTrue($log->hasWarning('The retention run lost its lock and stopped before its next rule.'));
    }

    /**
     * @param list<RetentionPurger> $purgers
     */
    private function retentionRun(array $purgers, bool $withLogs = false): RetentionRun
    {
        if ($withLogs) {
            return new RetentionRun(new FixedScheduleSource(new RetentionSchedule([
                new RetentionRule('R-1', 'trash', [], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
                new RetentionRule('R-3', 'logs', [], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            ])), $purgers);
        }

        return new RetentionRun(new FixedScheduleSource(new RetentionSchedule([
            new RetentionRule('R-1', 'trash', [], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-2', 'accounts', [], RetentionPeriod::untilEvent('account closed'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
        ])), $purgers);
    }
}
