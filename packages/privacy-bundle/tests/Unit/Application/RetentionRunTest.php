<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\RetentionRun;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedScheduleSource;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingPurger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetentionRun::class)]
#[CoversClass(MisconfiguredPrivacy::class)]
final class RetentionRunTest extends TestCase
{
    public function testEachOrderGoesToThePurgerOfItsStore(): void
    {
        $clicks = new RecordingPurger('clicks', purged: 5);
        $history = new RecordingPurger('history', purged: 0);

        $purged = new RetentionRun($this->schedule(), [$history, $clicks])->run(new DateTimeImmutable('2026-10-31T00:00:00Z'));

        self::assertSame(['R-1' => 5, 'R-2' => 0], $purged);
        self::assertCount(1, $clicks->orders);
        self::assertSame('2024-10-31', $clicks->orders[0]->cutoff->format('Y-m-d'));
        self::assertSame('R-2', $history->orders[0]->ruleId);
    }

    /**
     * A job line with nothing behind it is a period stated and not enforced: refused before any store is purged.
     */
    public function testAJobLineWithoutAPurgerStopsTheRunBeforeAnythingIsPurged(): void
    {
        $clicks = new RecordingPurger('clicks');

        try {
            new RetentionRun($this->schedule(), [$clicks])->run(new DateTimeImmutable('2026-10-31T00:00:00Z'));
            self::fail('A job line without a purger must not pass.');
        } catch (MisconfiguredPrivacy $misconfiguredPrivacy) {
            self::assertStringContainsString('"history"', $misconfiguredPrivacy->getMessage());
            self::assertStringContainsString('R-2', $misconfiguredPrivacy->getMessage());
            self::assertSame([], $clicks->orders);
        }
    }

    public function testTwoPurgersForOneStoreAreRefused(): void
    {
        $this->expectException(MisconfiguredPrivacy::class);

        new RetentionRun($this->schedule(), [new RecordingPurger('clicks'), new RecordingPurger('clicks')]);
    }

    private function schedule(): FixedScheduleSource
    {
        return new FixedScheduleSource(new RetentionSchedule([
            new RetentionRule('R-1', 'clicks', [], RetentionPeriod::fixed('P24M'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-2', 'history', [], RetentionPeriod::fixed('P90D'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-3', 'logs', [], RetentionPeriod::fixed('P90D'), Mechanism::Rotation, ErasureEffect::AwaitsExpiry, AccessResponse::AsCategory),
        ]));
    }
}
