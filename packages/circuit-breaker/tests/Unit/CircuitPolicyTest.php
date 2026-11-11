<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Unit;

use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircuitPolicy::class)]
final class CircuitPolicyTest extends TestCase
{
    /**
     * @param array{int, int, int, int, int, 5?: ?int, 6?: int} $arguments
     */
    #[TestWith([[0, 5, 60, 30, 1]])]
    #[TestWith([[101, 5, 60, 30, 1]])]
    #[TestWith([[50, 0, 60, 30, 1]])]
    #[TestWith([[50, 5, 0, 30, 1]])]
    #[TestWith([[50, 5, 60, 0, 1]])]
    #[TestWith([[50, 5, 60, 30, 0]])]
    #[TestWith([[50, 5, 60, 30, 1, 0]])]
    #[TestWith([[50, 5, 60, 30, 1, 200, 0]])]
    #[TestWith([[50, 5, 60, 30, 1, 200, 101]])]
    #[TestWith([[50, 5, 60, 30, 1, null, 0]])]
    public function testAnOutOfRangeValueIsRefused(array $arguments): void
    {
        $this->expectException(InvalidCircuit::class);

        CircuitPolicy::of(...$arguments);
    }

    public function testTheBoundsAreAccepted(): void
    {
        self::assertSame(1, CircuitPolicy::of(1, 1, 1, 1)->failureRatePercent);
        self::assertSame(100, CircuitPolicy::of(100, 1, 1, 1)->failureRatePercent);
    }

    public function testOneHalfOpenSuccessClosesByDefault(): void
    {
        self::assertSame(1, CircuitPolicy::of(50, 5, 60, 30)->halfOpenSuccesses);
    }

    /**
     * The minimum number of calls is the denominator: one failure out of one call does not trip.
     */
    public function testBelowTheMinimumNothingTrips(): void
    {
        $policy = CircuitPolicy::of(50, 5, 60, 30);

        self::assertFalse($policy->trips(4, 4));
        self::assertTrue($policy->trips(5, 3));
    }

    public function testTheThresholdIsInclusiveAndExact(): void
    {
        $policy = CircuitPolicy::of(50, 2, 60, 30);

        self::assertTrue($policy->trips(10, 5));
        self::assertFalse($policy->trips(10, 4));
        // 1/3 is 33.33 %: above 33, below 34, decided without a float.
        self::assertTrue(CircuitPolicy::of(33, 1, 60, 30)->trips(3, 1));
        self::assertFalse(CircuitPolicy::of(34, 1, 60, 30)->trips(3, 1));
        // 1/101 is just under 1 %.
        self::assertFalse(CircuitPolicy::of(1, 1, 60, 30)->trips(101, 1));
    }

    public function testNoCallIsSlowByDefault(): void
    {
        $policy = CircuitPolicy::of(50, 5, 60, 30);

        self::assertNull($policy->slowCallMilliseconds);
        self::assertSame(100, $policy->slowCallRatePercent);
        self::assertFalse($policy->isSlow(CallOutcome::succeeded(\PHP_INT_MAX)));
        self::assertFalse($policy->trips(5, 0, 5), 'slow calls trip only with a threshold');
    }

    public function testACallIsSlowWhenItLastsLongerThanTheThreshold(): void
    {
        $policy = CircuitPolicy::of(50, 5, 60, 30, 1, 1);

        self::assertSame(1, $policy->slowCallMilliseconds);
        self::assertFalse($policy->isSlow(CallOutcome::succeeded(1)));
        self::assertTrue($policy->isSlow(CallOutcome::succeeded(2)));
        self::assertTrue($policy->isSlow(CallOutcome::failed(2)));
        self::assertFalse($policy->isSlow(CallOutcome::failed()), 'a call not measured is never slow');
    }

    public function testTheSlowCallRateIsInclusiveAndExact(): void
    {
        $policy = CircuitPolicy::of(100, 4, 60, 30, 1, 200, 50);

        self::assertSame(50, $policy->slowCallRatePercent);
        self::assertTrue($policy->trips(10, 0, 5));
        self::assertFalse($policy->trips(10, 0, 4));
        self::assertFalse($policy->trips(3, 0, 3), 'the minimum is the denominator of slow calls too');
        self::assertTrue(CircuitPolicy::of(100, 1, 60, 30, 1, 200, 1)->trips(100, 0, 1));
        self::assertTrue(CircuitPolicy::of(100, 1, 60, 30, 1, 200)->trips(3, 0, 3));
        self::assertFalse(CircuitPolicy::of(100, 1, 60, 30, 1, 200)->trips(3, 0, 2));
        // 100/101 is just under 100 %.
        self::assertFalse(CircuitPolicy::of(100, 1, 60, 30, 1, 200)->trips(101, 0, 100));
        self::assertFalse(CircuitPolicy::of(100, 1, 60, 30, 1, 200)->trips(1, 0), 'without slow calls given, none is counted');
    }
}
