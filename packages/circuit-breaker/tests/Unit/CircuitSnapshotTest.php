<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Unit;

use DateTimeImmutable;
use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\CircuitSnapshot;
use Phil\CircuitBreaker\CircuitState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircuitSnapshot::class)]
final class CircuitSnapshotTest extends TestCase
{
    private CircuitPolicy $policy;

    private DateTimeImmutable $t0;

    protected function setUp(): void
    {
        // Opens at 50 % failures over at least 4 calls in a 60 s window; open 30 s; 2 trial successes close.
        $this->policy = CircuitPolicy::of(50, 4, 60, 30, 2);
        $this->t0 = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    }

    public function testANewCircuitIsClosedAndPermitsCalls(): void
    {
        $snapshot = CircuitSnapshot::closed($this->t0);

        self::assertSame(CircuitState::Closed, $snapshot->state);
        self::assertTrue($snapshot->permitsCalls());
    }

    public function testItOpensWhenTheRateIsReachedOverTheMinimum(): void
    {
        $snapshot = CircuitSnapshot::closed($this->t0)
            ->recordSuccess($this->policy, $this->plus(1))
            ->recordSuccess($this->policy, $this->plus(2))
            ->recordFailure($this->policy, $this->plus(3));

        self::assertSame(CircuitState::Closed, $snapshot->state, '1 failure out of 3 calls is under the minimum');

        $snapshot = $snapshot->recordFailure($this->policy, $this->plus(4));

        self::assertSame(CircuitState::Open, $snapshot->state);
        self::assertFalse($snapshot->permitsCalls());
        self::assertEquals($this->plus(4), $snapshot->since);
    }

    public function testFailuresUnderTheMinimumNeverOpen(): void
    {
        $snapshot = CircuitSnapshot::closed($this->t0);
        for ($i = 1; $i <= 3; ++$i) {
            $snapshot = $snapshot->recordFailure($this->policy, $this->plus($i));
        }

        self::assertSame(CircuitState::Closed, $snapshot->state);
        self::assertSame(3, $snapshot->failures);
    }

    /**
     * A fixed window: three failures, then the window elapses, and the fourth failure starts a new count.
     */
    public function testTheWindowRestartsAfterItElapses(): void
    {
        $snapshot = CircuitSnapshot::closed($this->t0);
        for ($i = 1; $i <= 3; ++$i) {
            $snapshot = $snapshot->recordFailure($this->policy, $this->plus($i));
        }

        $snapshot = $snapshot->recordFailure($this->policy, $this->plus(60));

        self::assertSame(CircuitState::Closed, $snapshot->state);
        self::assertSame(1, $snapshot->calls);
        self::assertSame(1, $snapshot->failures);
    }

    public function testItStaysOpenUntilTheOpenDurationElapses(): void
    {
        $open = $this->opened();

        self::assertSame(CircuitState::Open, $open->at($this->policy, $this->plus(29))->state);
        self::assertSame(CircuitState::HalfOpen, $open->at($this->policy, $this->plus(30))->state);
        self::assertTrue($open->at($this->policy, $this->plus(30))->permitsCalls());
    }

    public function testAnUnchangedSnapshotIsReturnedAsIs(): void
    {
        $open = $this->opened();

        self::assertSame($open, $open->at($this->policy, $this->plus(10)));
    }

    public function testEnoughTrialSuccessesCloseTheCircuit(): void
    {
        $halfOpen = $this->opened()->at($this->policy, $this->plus(30));

        $one = $halfOpen->recordSuccess($this->policy, $this->plus(31));
        self::assertSame(CircuitState::HalfOpen, $one->state);
        self::assertSame(1, $one->halfOpenSuccesses);

        $closed = $one->recordSuccess($this->policy, $this->plus(32));
        self::assertSame(CircuitState::Closed, $closed->state);
        self::assertSame(0, $closed->calls);
        self::assertEquals($this->plus(32), $closed->since);
    }

    public function testOneTrialFailureOpensItAgainForAFullPeriod(): void
    {
        $reopened = $this->opened()
            ->at($this->policy, $this->plus(30))
            ->recordSuccess($this->policy, $this->plus(31))
            ->recordFailure($this->policy, $this->plus(35));

        self::assertSame(CircuitState::Open, $reopened->state);
        self::assertSame(CircuitState::Open, $reopened->at($this->policy, $this->plus(64))->state);
        self::assertSame(CircuitState::HalfOpen, $reopened->at($this->policy, $this->plus(65))->state);
    }

    /**
     * A recorded outcome after the open duration goes through the half-open state first.
     */
    public function testAnOutcomeRecordedAfterTheOpenDurationIsATrialCall(): void
    {
        $policy = CircuitPolicy::of(50, 4, 60, 30, 1);

        self::assertSame(CircuitState::Closed, $this->opened()->recordSuccess($policy, $this->plus(40))->state);
        self::assertSame(CircuitState::Open, $this->opened()->recordFailure($policy, $this->plus(40))->state);
    }

    public function testOutcomesWhileOpenChangeNothing(): void
    {
        $open = $this->opened();

        self::assertSame($open, $open->recordSuccess($this->policy, $this->plus(5)));
        self::assertSame($open, $open->recordFailure($this->policy, $this->plus(5)));
    }

    public function testARestoredSnapshotIsClampedToConsistentCounters(): void
    {
        $snapshot = CircuitSnapshot::restore(CircuitState::Closed, $this->t0, 2, 5, -1);

        self::assertSame(2, $snapshot->calls);
        self::assertSame(2, $snapshot->failures);
        self::assertSame(0, $snapshot->halfOpenSuccesses);
    }

    public function testEveryNewStateStartsWithEmptyCounters(): void
    {
        $this->assertCounters(CircuitSnapshot::closed($this->t0), 0, 0, 0);

        $open = $this->opened();
        $this->assertCounters($open, 0, 0, 0);

        $halfOpen = $open->at($this->policy, $this->plus(30));
        $this->assertCounters($halfOpen, 0, 0, 0);
        $this->assertCounters($halfOpen->recordSuccess($this->policy, $this->plus(31)), 0, 0, 1);
    }

    public function testAClosedCircuitCountsItsCallsAndFailuresOnly(): void
    {
        $snapshot = CircuitSnapshot::closed($this->t0)->recordSuccess($this->policy, $this->plus(1));
        $this->assertCounters($snapshot, 1, 0, 0);

        $snapshot = $snapshot->recordFailure($this->policy, $this->plus(2));
        $this->assertCounters($snapshot, 2, 1, 0);
    }

    /**
     * One failure out of four calls is 25 %, under the 50 % threshold, even though the minimum is reached.
     */
    public function testOneFailureCountsOnce(): void
    {
        $snapshot = CircuitSnapshot::closed($this->t0);
        for ($i = 1; $i <= 3; ++$i) {
            $snapshot = $snapshot->recordSuccess($this->policy, $this->plus($i));
        }

        $snapshot = $snapshot->recordFailure($this->policy, $this->plus(4));

        self::assertSame(CircuitState::Closed, $snapshot->state);
        $this->assertCounters($snapshot, 4, 1, 0);
    }

    public function testARestoredSnapshotNeverHoldsNegativeCounters(): void
    {
        $this->assertCounters(CircuitSnapshot::restore(CircuitState::Closed, $this->t0, -3, -2, 0), 0, 0, 0);
        $this->assertCounters(CircuitSnapshot::restore(CircuitState::Closed, $this->t0, 0, 0, 0), 0, 0, 0);
        $this->assertCounters(CircuitSnapshot::restore(CircuitState::HalfOpen, $this->t0, 3, 1, 1), 3, 1, 1);
    }

    /**
     * 50 % failures or 50 % calls over 200 ms, over at least 4 calls: two slow successes out of four open the circuit.
     */
    public function testSlowCallsOpenTheCircuitWhenTheirRateIsReached(): void
    {
        $policy = CircuitPolicy::of(50, 4, 60, 30, 2, 200, 50);
        $snapshot = CircuitSnapshot::closed($this->t0)
            ->record($policy, CallOutcome::succeeded(200), $this->plus(1))
            ->record($policy, CallOutcome::succeeded(201), $this->plus(2))
            ->record($policy, CallOutcome::succeeded(), $this->plus(3));

        self::assertSame([CircuitState::Closed, 3, 0, 1], [$snapshot->state, $snapshot->calls, $snapshot->failures, $snapshot->slowCalls], 'a call that lasts the threshold is not slow');

        $opened = $snapshot->record($policy, CallOutcome::succeeded(5000), $this->plus(4));

        self::assertSame(CircuitState::Open, $opened->state);
        self::assertEquals($this->plus(4), $opened->since);
        self::assertSame(0, $opened->slowCalls);
    }

    public function testASlowFailureCountsAsBothAFailureAndASlowCall(): void
    {
        $policy = CircuitPolicy::of(100, 4, 60, 30, 1, 200, 100);
        $snapshot = CircuitSnapshot::closed($this->t0)->record($policy, CallOutcome::failed(300), $this->plus(1));

        self::assertSame([1, 1, 1], [$snapshot->calls, $snapshot->failures, $snapshot->slowCalls]);
    }

    /**
     * A success that is not slow never trips, even when the window's rates are over their thresholds.
     */
    public function testOnlyABadCallOpensTheCircuit(): void
    {
        $policy = CircuitPolicy::of(50, 4, 60, 30, 1, 200, 50);
        $snapshot = CircuitSnapshot::restore(CircuitState::Closed, $this->t0, 3, 0, 0, 3)
            ->record($policy, CallOutcome::succeeded(10), $this->plus(1));

        self::assertSame([CircuitState::Closed, 4, 3], [$snapshot->state, $snapshot->calls, $snapshot->slowCalls]);
    }

    public function testWithoutAThresholdNoCallIsSlow(): void
    {
        $snapshot = CircuitSnapshot::closed($this->t0);
        for ($i = 1; $i <= 4; ++$i) {
            $snapshot = $snapshot->record($this->policy, CallOutcome::succeeded(60_000), $this->plus($i));
        }

        self::assertSame([CircuitState::Closed, 4, 0], [$snapshot->state, $snapshot->calls, $snapshot->slowCalls]);
    }

    public function testASlowTrialCallOpensTheCircuitAgain(): void
    {
        $policy = CircuitPolicy::of(50, 4, 60, 30, 2, 200, 100);
        $halfOpen = $this->opened()->at($policy, $this->plus(30));

        $reopened = $halfOpen->record($policy, CallOutcome::succeeded(201), $this->plus(31));
        self::assertSame(CircuitState::Open, $reopened->state);
        self::assertEquals($this->plus(31), $reopened->since);

        $counted = $halfOpen->record($policy, CallOutcome::succeeded(200), $this->plus(31));
        self::assertSame([CircuitState::HalfOpen, 1], [$counted->state, $counted->halfOpenSuccesses]);
        self::assertSame(CircuitState::Closed, $counted->record($policy, CallOutcome::succeeded(20), $this->plus(32))->state);
    }

    public function testAFreshWindowForgetsItsSlowCalls(): void
    {
        $policy = CircuitPolicy::of(50, 4, 60, 30, 1, 200, 50);
        $snapshot = CircuitSnapshot::closed($this->t0)->record($policy, CallOutcome::succeeded(300), $this->plus(1));

        self::assertSame(1, $snapshot->slowCalls);
        self::assertSame(0, $snapshot->at($policy, $this->plus(60))->slowCalls);
        self::assertSame(1, $snapshot->recordSuccess($policy, $this->plus(2))->slowCalls);
    }

    public function testARestoredSnapshotKeepsItsSlowCallsWithinItsCalls(): void
    {
        self::assertSame(2, CircuitSnapshot::restore(CircuitState::Closed, $this->t0, 3, 0, 0, 2)->slowCalls);
        self::assertSame(3, CircuitSnapshot::restore(CircuitState::Closed, $this->t0, 3, 0, 0, 9)->slowCalls);
        self::assertSame(0, CircuitSnapshot::restore(CircuitState::Closed, $this->t0, 3, 0, 0, -1)->slowCalls);
        self::assertSame(0, CircuitSnapshot::restore(CircuitState::Closed, $this->t0, 3, 0, 0)->slowCalls);
    }

    private function assertCounters(CircuitSnapshot $snapshot, int $calls, int $failures, int $halfOpenSuccesses): void
    {
        self::assertSame(
            [$calls, $failures, $halfOpenSuccesses],
            [$snapshot->calls, $snapshot->failures, $snapshot->halfOpenSuccesses],
        );
    }

    private function opened(): CircuitSnapshot
    {
        $snapshot = CircuitSnapshot::closed($this->t0);
        for ($i = 0; $i < 4; ++$i) {
            $snapshot = $snapshot->recordFailure($this->policy, $this->t0);
        }

        self::assertSame(CircuitState::Open, $snapshot->state);

        return $snapshot;
    }

    private function plus(int $seconds): DateTimeImmutable
    {
        return $this->t0->modify(\sprintf('+%d seconds', $seconds));
    }
}
