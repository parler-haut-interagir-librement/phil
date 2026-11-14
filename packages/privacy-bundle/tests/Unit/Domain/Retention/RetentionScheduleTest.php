<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Retention;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetentionSchedule::class)]
#[CoversClass(PurgeOrder::class)]
final class RetentionScheduleTest extends TestCase
{
    public function testOnlyJobLinesWithAFixedPeriodBecomePurgeOrders(): void
    {
        $schedule = new RetentionSchedule([
            $this->rule('R-1', 'clicks', RetentionPeriod::fixed('P24M'), Mechanism::Job),
            $this->rule('R-2', 'logs', RetentionPeriod::fixed('P90D'), Mechanism::Rotation),
            $this->rule('R-3', 'accounts', RetentionPeriod::untilEvent('dormancy'), Mechanism::Job),
            $this->rule('R-4', 'history', RetentionPeriod::fixed('P90D'), Mechanism::Job),
        ]);

        $orders = $schedule->purgeOrders(new DateTimeImmutable('2026-10-31T00:00:00Z'));

        self::assertSame(
            [['R-1', 'clicks', '2024-10-31'], ['R-4', 'history', '2026-08-02']],
            array_map(static fn (PurgeOrder $order): array => [$order->ruleId, $order->store, $order->cutoff->format('Y-m-d')], $orders),
        );
    }

    public function testAJobWithoutACutoffIsReported(): void
    {
        $schedule = new RetentionSchedule([
            $this->rule('R-1', 'clicks', RetentionPeriod::fixed('P24M'), Mechanism::Job),
            $this->rule('R-3', 'accounts', RetentionPeriod::notYetFixed('thresholds'), Mechanism::Job),
            $this->rule('R-5', 'boxes', RetentionPeriod::untilEvent('manual purge'), Mechanism::Manual),
        ]);

        self::assertSame(['R-3'], array_map(static fn (RetentionRule $rule): string => $rule->id, $schedule->jobsWithoutCutoff()));
    }

    public function testRulesAreFoundByIdentifierAndKeptInOrder(): void
    {
        $schedule = new RetentionSchedule([$this->rule('R-2', 'b', RetentionPeriod::indefinite(), Mechanism::Manual), $this->rule('R-1', 'a', RetentionPeriod::indefinite(), Mechanism::Manual)]);

        self::assertTrue($schedule->has('R-1'));
        self::assertFalse($schedule->has('R-9'));
        self::assertSame('a', $schedule->rule('R-1')->store);
        self::assertSame(['R-2', 'R-1'], array_map(static fn (RetentionRule $rule): string => $rule->id, $schedule->rules()));
    }

    public function testAnUnknownRuleIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        new RetentionSchedule([])->rule('R-1');
    }

    /**
     * Documents cite a line by its identifier; two lines under one identifier make every citation ambiguous.
     */
    public function testADuplicateIdentifierIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('defines rule R-1 twice');

        new RetentionSchedule([$this->rule('R-1', 'a', RetentionPeriod::indefinite(), Mechanism::Manual), $this->rule('R-1', 'b', RetentionPeriod::indefinite(), Mechanism::Manual)]);
    }

    private function rule(string $id, string $store, RetentionPeriod $period, Mechanism $mechanism): RetentionRule
    {
        return new RetentionRule($id, $store, [], $period, $mechanism, ErasureEffect::Deleted, AccessResponse::Returned);
    }
}
