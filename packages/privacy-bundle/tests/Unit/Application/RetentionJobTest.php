<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Application\RetentionRun;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedScheduleSource;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingPurger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(RetentionJob::class)]
#[CoversClass(RetentionRun::class)]
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

    public function testTheLockLastsAnHour(): void
    {
        $store = new RecordingLockStore();
        new RetentionJob($this->retentionRun([new RecordingPurger('trash')]), new LockFactory($store), new MockClock())->run();

        self::assertSame([3600.0], $store->lifetimes);
    }

    /**
     * @param list<RecordingPurger> $purgers
     */
    private function retentionRun(array $purgers): RetentionRun
    {
        return new RetentionRun(new FixedScheduleSource(new RetentionSchedule([
            new RetentionRule('R-1', 'trash', [], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-2', 'accounts', [], RetentionPeriod::untilEvent('account closed'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
        ])), $purgers);
    }
}
