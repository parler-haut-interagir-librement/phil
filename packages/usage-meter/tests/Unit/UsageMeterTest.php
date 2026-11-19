<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\Exception\UnknownMeter;
use Phil\UsageMeter\Exception\UsageMeterException;
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\LimitChain;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\BillingCycle;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\Period\RollingWindow;
use Phil\UsageMeter\Tests\Unit\Fixture\FixedAnchors;
use Phil\UsageMeter\Tests\Unit\Fixture\FixedLimits;
use Phil\UsageMeter\Usage;
use Phil\UsageMeter\UsageMeter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(UsageMeter::class)]
#[CoversClass(Usage::class)]
#[CoversClass(QuotaExceeded::class)]
#[CoversClass(LimitChain::class)]
final class UsageMeterTest extends TestCase
{
    private MockClock $clock;

    private InMemoryUsageStore $store;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-03-14 10:30:00', 'UTC');
        $this->store = new InMemoryUsageStore();
    }

    public function testANewSubjectHasUsedNothing(): void
    {
        $usage = $this->meter()->usage('account-1', 'exports');

        self::assertSame('account-1', $usage->subject);
        self::assertSame('exports', $usage->meter);
        self::assertSame(0, $usage->used);
        self::assertSame(3, $usage->limit->value);
        self::assertSame(3, $usage->remaining());
        self::assertSame('2026-03-01T00:00:00+00:00', $usage->start->format(\DATE_ATOM));
        self::assertSame('2026-04-01T00:00:00+00:00', $usage->resetsAt->format(\DATE_ATOM));
        self::assertFalse($usage->replayed);
        self::assertFalse($usage->isExceeded());
        self::assertTrue($usage->allows());
        self::assertTrue($usage->allows(3));
        self::assertFalse($usage->allows(4));
    }

    public function testConsumingCountsUpToTheQuotaThenRefuses(): void
    {
        $meter = $this->meter();

        self::assertSame(2, $meter->consume('account-1', 'exports', 2)->used);
        self::assertSame(3, $meter->consume('account-1', 'exports')->used);
        self::assertFalse($meter->allows('account-1', 'exports'));

        try {
            $meter->consume('account-1', 'exports');
            self::fail('The quota was exceeded.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(3, $quotaExceeded->usage->used);
            self::assertSame(1, $quotaExceeded->requested);
            self::assertSame(0, $quotaExceeded->usage->remaining());
            self::assertSame('The quota of "exports" is reached: 3 used of 3, 1 more requested; the window changes at 2026-04-01T00:00:00+00:00.', $quotaExceeded->getMessage());
            self::assertInstanceOf(UsageMeterException::class, $quotaExceeded);
            self::assertInstanceOf(RuntimeException::class, $quotaExceeded);
        }

        self::assertSame(3, $meter->usage('account-1', 'exports')->used);
    }

    public function testARefusedAmountCountsNothingAndASmallerOneFits(): void
    {
        $meter = $this->meter();
        $meter->consume('account-1', 'exports', 2);

        try {
            $meter->consume('account-1', 'exports', 2);
            self::fail('The quota was exceeded.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(2, $quotaExceeded->requested);
        }

        self::assertTrue($meter->allows('account-1', 'exports', 1));
        self::assertSame(3, $meter->consume('account-1', 'exports', 1)->used);
    }

    public function testEachSubjectHasItsOwnCount(): void
    {
        $meter = $this->meter();
        $meter->consume('account-1', 'exports', 3);

        self::assertSame(1, $meter->consume('account-2', 'exports')->used);
        self::assertSame(3, $meter->usage('account-1', 'exports')->used);
    }

    public function testTheCountStartsAgainWithThePeriod(): void
    {
        $meter = $this->meter();
        $meter->consume('account-1', 'exports', 3);

        $this->clock->modify('2026-04-01 00:00:00');

        self::assertSame(0, $meter->usage('account-1', 'exports')->used);
        self::assertSame(1, $meter->consume('account-1', 'exports')->used);
        self::assertSame('2026-05-01T00:00:00+00:00', $meter->usage('account-1', 'exports')->resetsAt->format(\DATE_ATOM));
    }

    public function testAKeyIsCountedOnceAndAnsweredAsReplayed(): void
    {
        $meter = $this->meter();

        self::assertFalse($meter->consume('account-1', 'exports', 1, 'export-42')->replayed);
        $replayed = $meter->consume('account-1', 'exports', 1, 'export-42');

        self::assertTrue($replayed->replayed);
        self::assertSame(1, $replayed->used);
    }

    public function testAReplayedKeySucceedsEvenWhenTheQuotaIsFull(): void
    {
        $meter = $this->meter();
        $meter->consume('account-1', 'exports', 1, 'export-42');
        $meter->consume('account-1', 'exports', 2);

        self::assertTrue($meter->consume('account-1', 'exports', 1, 'export-42')->replayed);
    }

    public function testARefusedKeyMayBeTriedAgain(): void
    {
        $meter = $this->meter();
        $meter->consume('account-1', 'exports', 3);

        try {
            $meter->consume('account-1', 'exports', 1, 'export-42');
            self::fail('The quota was exceeded.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertFalse($quotaExceeded->usage->replayed);
        }

        $this->clock->modify('2026-04-01 00:00:00');

        self::assertFalse($meter->consume('account-1', 'exports', 1, 'export-42')->replayed);
    }

    public function testTheDefaultAmountIsOne(): void
    {
        $meter = $this->meter();
        $meter->consume('account-1', 'exports', 2);

        self::assertTrue($meter->allows('account-1', 'exports'));
        self::assertTrue($meter->usage('account-1', 'exports')->allows());
        self::assertFalse($meter->usage('account-1', 'exports')->allows(2));

        $meter->consume('account-1', 'exports');

        self::assertFalse($meter->usage('account-1', 'exports')->allows());
        self::assertTrue($meter->usage('account-1', 'exports')->allows(0));
    }

    public function testAWindowIsExceededOnlyPastItsLimit(): void
    {
        $meter = $this->meter();

        self::assertFalse($meter->record('account-1', 'exports', 3)->isExceeded());
        self::assertTrue($meter->record('account-1', 'exports')->isExceeded());
        self::assertFalse($meter->record('account-1', 'tokens', 1000)->isExceeded());
    }

    public function testRecordingCountsPastTheQuota(): void
    {
        $meter = $this->meter();
        $meter->record('account-1', 'exports', 2);

        $usage = $meter->record('account-1', 'exports', 5, 'job-7');

        self::assertSame(7, $usage->used);
        self::assertTrue($usage->isExceeded());
        self::assertSame(0, $usage->remaining());
        self::assertFalse($usage->replayed);
        self::assertTrue($meter->record('account-1', 'exports', 5, 'job-7')->replayed);
        self::assertSame(7, $meter->usage('account-1', 'exports')->used);
        self::assertSame(3, $meter->record('account-1', 'exports')->limit->value);
        self::assertSame(8, $meter->usage('account-1', 'exports')->used);

        $this->expectException(QuotaExceeded::class);
        $meter->consume('account-1', 'exports');
    }

    public function testAnUnlimitedMeterNeverRefuses(): void
    {
        $meter = $this->meter();

        self::assertSame(1000, $meter->consume('account-1', 'tokens', 1000)->used);
        self::assertNull($meter->usage('account-1', 'tokens')->remaining());
        self::assertTrue($meter->allows('account-1', 'tokens', \PHP_INT_MAX));
    }

    public function testAProviderLimitWinsOverTheMeterOwn(): void
    {
        $meter = $this->meter(new LimitChain(
            new FixedLimits(['account-1' => ['exports' => Limit::of(10)]]),
            new FixedLimits(['account-1' => ['exports' => Limit::of(1), 'tokens' => Limit::of(50)], 'account-2' => ['exports' => Limit::of(0)]]),
        ));

        self::assertSame(10, $meter->limit('account-1', 'exports')->value);
        self::assertSame(50, $meter->limit('account-1', 'tokens')->value);
        self::assertSame(0, $meter->limit('account-2', 'exports')->value);
        self::assertSame(3, $meter->limit('account-3', 'exports')->value);
        self::assertSame(10, $meter->consume('account-1', 'exports', 10)->used);
        self::assertFalse($meter->allows('account-2', 'exports'));
        self::assertSame(3, new UsageMeter($this->meters(), $this->store, $this->clock, new LimitChain())->limit('account-1', 'exports')->value);
    }

    public function testTheSubjectAnchorStartsItsBillingPeriods(): void
    {
        $meters = new Meters(new Meter('projects', BillingCycle::monthly(new DateTimeImmutable('2026-01-01T00:00:00+00:00')), Limit::of(2)));
        $meter = new UsageMeter($meters, $this->store, $this->clock, null, new FixedAnchors(['account-1' => new DateTimeImmutable('2026-02-20T00:00:00+00:00')]));

        $meter->consume('account-1', 'projects', 2);
        $meter->consume('account-2', 'projects', 2);

        self::assertSame('2026-02-20T00:00:00+00:00', $meter->usage('account-1', 'projects')->start->format(\DATE_ATOM));
        self::assertSame('2026-03-01T00:00:00+00:00', $meter->usage('account-2', 'projects')->start->format(\DATE_ATOM));

        $this->clock->modify('2026-03-20 00:00:00');

        self::assertSame(0, $meter->usage('account-1', 'projects')->used);
        self::assertSame(2, $meter->usage('account-2', 'projects')->used);
    }

    public function testARollingWindowCountsItsEarlierBucketsAndFreesThemAsTheyLeave(): void
    {
        $meters = new Meters(new Meter('requests', RollingWindow::hours(3), Limit::of(10)));
        $meter = new UsageMeter($meters, $this->store, $this->clock);

        $meter->consume('account-1', 'requests', 6);

        $this->clock->modify('2026-03-14 11:30:00');
        $meter->consume('account-1', 'requests', 3);
        $this->clock->modify('2026-03-14 12:30:00');

        self::assertSame(9, $meter->usage('account-1', 'requests')->used);
        self::assertSame('2026-03-14T13:00:00+00:00', $meter->usage('account-1', 'requests')->resetsAt->format(\DATE_ATOM));

        try {
            $meter->consume('account-1', 'requests', 2);
            self::fail('The window was exceeded.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(9, $quotaExceeded->usage->used);
        }

        self::assertSame(10, $meter->consume('account-1', 'requests')->used);

        $this->clock->modify('2026-03-14 13:00:00');

        self::assertSame(4, $meter->usage('account-1', 'requests')->used);
        self::assertSame(10, $meter->consume('account-1', 'requests', 6)->used);
    }

    public function testThePurgeForgetsWhatNoWindowCounts(): void
    {
        $meter = $this->meter();
        $meter->consume('account-1', 'exports', 1, 'export-1');

        self::assertSame(0, $meter->purge());

        $this->clock->modify('2026-04-01 00:00:00');

        self::assertSame(2, $meter->purge());
        self::assertFalse($meter->consume('account-1', 'exports', 1, 'export-1')->replayed);
    }

    public function testTheStoreReceivesTheCounterOfTheWindow(): void
    {
        $this->meter()->consume('account-1', 'exports', 1, 'export-1');

        self::assertSame(1, $this->store->total('account-1', 'exports', new DateTimeImmutable('2026-03-01T00:00:00+00:00'), new DateTimeImmutable('2026-03-01T00:00:01+00:00')));
        self::assertSame(0, $this->store->purge(new DateTimeImmutable('2026-03-31T23:59:59+00:00')));
        self::assertSame(2, $this->store->purge(new DateTimeImmutable('2026-04-01T00:00:00+00:00')));

        $this->meter()->record('account-1', 'exports', 1);

        self::assertSame(1, $this->store->total('account-1', 'exports', new DateTimeImmutable('2026-03-01T00:00:00+00:00'), new DateTimeImmutable('2026-03-01T00:00:01+00:00')));
        self::assertSame(1, $this->store->purge(new DateTimeImmutable('2026-04-01T00:00:00+00:00')));
        self::assertSame(0, $this->store->purge(new DateTimeImmutable('2026-04-01T00:00:00+00:00')));
    }

    public function testARefundGivesBackWhatAFailedActionConsumed(): void
    {
        $meter = $this->meter();
        $meter->consume('account-1', 'exports', 3, 'export-42');

        $usage = $meter->refund('account-1', 'exports', 'export-42');

        self::assertSame(0, $usage->used);
        self::assertFalse($usage->replayed);
        self::assertSame('2026-04-01T00:00:00+00:00', $usage->resetsAt->format(\DATE_ATOM));
        self::assertSame(3, $meter->consume('account-1', 'exports', 3)->used);
    }

    public function testARefundIsAnsweredAsReplayedWhenItGivesNothingBack(): void
    {
        $meter = $this->meter();
        $meter->record('account-1', 'exports', 5, 'export-42');

        self::assertSame(3, $meter->refund('account-1', 'exports', 'export-42', 2)->used);
        $again = $meter->refund('account-1', 'exports', 'export-42', 2);

        self::assertTrue($again->replayed);
        self::assertSame(3, $again->used);
        self::assertTrue($meter->refund('account-1', 'exports', 'unknown')->replayed);
        self::assertSame(0, $meter->refund('account-1', 'exports', 'export-42')->used);
    }

    public function testARefundUsesTheSubjectLimit(): void
    {
        $usage = $this->meter(new LimitChain(new FixedLimits(['account-1' => ['exports' => Limit::of(9)]])))->refund('account-1', 'exports', 'none');

        self::assertSame(9, $usage->limit->value);
    }

    public function testAnUnknownMeterIsAnError(): void
    {
        $this->expectException(UnknownMeter::class);

        $this->meter()->consume('account-1', 'imports');
    }

    /**
     * @return iterable<string, array{Closure(UsageMeter): mixed, string}>
     */
    public static function invalidCalls(): iterable
    {
        $tooLong = str_repeat('a', 256);

        yield 'empty subject on usage' => [static fn (UsageMeter $meter): Usage => $meter->usage('', 'exports'), 'A subject is named by 1 to 255 bytes.'];
        yield 'long subject on limit' => [static fn (UsageMeter $meter): Limit => $meter->limit($tooLong, 'exports'), 'A subject is named by 1 to 255 bytes.'];
        yield 'empty subject on consume' => [static fn (UsageMeter $meter): Usage => $meter->consume('', 'exports'), 'A subject is named by 1 to 255 bytes.'];
        yield 'long subject on record' => [static fn (UsageMeter $meter): Usage => $meter->record($tooLong, 'exports'), 'A subject is named by 1 to 255 bytes.'];
        yield 'zero on consume' => [static fn (UsageMeter $meter): Usage => $meter->consume('a', 'exports', 0), 'An amount counted is one or more; got 0.'];
        yield 'negative on record' => [static fn (UsageMeter $meter): Usage => $meter->record('a', 'exports', -2), 'An amount counted is one or more; got -2.'];
        yield 'zero on allows' => [static fn (UsageMeter $meter): bool => $meter->allows('a', 'exports', 0), 'An amount counted is one or more; got 0.'];
        yield 'empty key on consume' => [static fn (UsageMeter $meter): Usage => $meter->consume('a', 'exports', 1, ''), 'An idempotency key is 1 to 255 bytes.'];
        yield 'empty key on refund' => [static fn (UsageMeter $meter): Usage => $meter->refund('a', 'exports', ''), 'An idempotency key is 1 to 255 bytes.'];
        yield 'long key on refund' => [static fn (UsageMeter $meter): Usage => $meter->refund('a', 'exports', $tooLong), 'An idempotency key is 1 to 255 bytes.'];
        yield 'zero on refund' => [static fn (UsageMeter $meter): Usage => $meter->refund('a', 'exports', 'k', 0), 'An amount counted is one or more; got 0.'];
        yield 'empty subject on refund' => [static fn (UsageMeter $meter): Usage => $meter->refund('', 'exports', 'k'), 'A subject is named by 1 to 255 bytes.'];
        yield 'long key on record' => [static fn (UsageMeter $meter): Usage => $meter->record('a', 'exports', 1, $tooLong), 'An idempotency key is 1 to 255 bytes.'];
    }

    /**
     * @param Closure(UsageMeter): mixed $call
     */
    #[DataProvider('invalidCalls')]
    public function testInvalidCallsAreRefused(Closure $call, string $message): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains($message);

        $call($this->meter());
    }

    public function testTheLongestSubjectAndKeyAreAccepted(): void
    {
        $longest = str_repeat('a', 255);

        self::assertSame(1, $this->meter()->consume($longest, 'exports', 1, $longest)->used);
        self::assertSame($longest, $this->meter()->usage($longest, 'exports')->subject);
    }

    private function meter(?LimitChain $limits = null): UsageMeter
    {
        return new UsageMeter($this->meters(), $this->store, $this->clock, $limits);
    }

    private function meters(): Meters
    {
        return new Meters(
            new Meter('exports', CalendarPeriod::month(), Limit::of(3)),
            new Meter('tokens', CalendarPeriod::month(), Limit::unlimited()),
        );
    }
}
