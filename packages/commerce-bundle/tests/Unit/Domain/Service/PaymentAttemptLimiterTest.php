<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Service;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Service\PaymentAttemptLimiter;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentAttemptLimiter::class)]
final class PaymentAttemptLimiterTest extends TestCase
{
    private const string START = '2026-01-01T12:00:00Z';

    public function testAKeyWithoutStateIsAllowed(): void
    {
        self::assertTrue($this->limiter()->decide(null, $this->moment(0))->isAllowed());
    }

    public function testAttemptsWithinTheBudgetAndWithoutPenaltyAreAllowed(): void
    {
        $limiter = $this->limiter();
        $state = $limiter->record(null, $this->moment(0), false);
        $state = $limiter->record($state, $this->moment(1), false);

        self::assertSame(2, $state->attempts);
        self::assertSame(0, $state->cumulativeWaitSeconds);
        self::assertTrue($limiter->decide($state, $this->moment(2))->isAllowed());
    }

    /**
     * The budget spent, the key waits for its state to expire, one window after its last attempt.
     */
    public function testASpentBudgetWaitsUntilTheStateExpires(): void
    {
        $limiter = $this->limiter();
        $state = null;

        foreach ([0, 1, 2] as $second) {
            $state = $limiter->record($state, $this->moment($second), false);
        }

        $decision = $limiter->decide($state, $this->moment(3));

        self::assertEquals($this->moment(2 + 100), $decision->retryAt);
        self::assertTrue($limiter->decide($state, $this->moment(2 + 100))->isAllowed());
    }

    public function testAPenaltyAddsOneStepOfWait(): void
    {
        $limiter = $this->limiter();
        $state = $limiter->record(null, $this->moment(0), true);

        self::assertSame(10, $state->cumulativeWaitSeconds);
        self::assertEquals($this->moment(10), $limiter->decide($state, $this->moment(5))->retryAt);
        self::assertTrue($limiter->decide($state, $this->moment(10))->isAllowed());
    }

    /**
     * Penalties melt into one cumulative wait: the second penalised attempt waits two steps.
     */
    public function testPenaltiesAccumulateIntoOneWait(): void
    {
        $limiter = $this->limiter();
        $state = $limiter->record(null, $this->moment(0), true);
        $state = $limiter->record($state, $this->moment(10), true);

        self::assertSame(20, $state->cumulativeWaitSeconds);
        self::assertEquals($this->moment(30), $limiter->decide($state, $this->moment(15))->retryAt);
    }

    /**
     * A non-penalised attempt keeps the wait already accumulated: the state does not forget a penalty
     * before it expires.
     */
    public function testAnAttemptWithoutPenaltyKeepsTheAccumulatedWait(): void
    {
        $limiter = $this->limiter();
        $state = $limiter->record(null, $this->moment(0), true);
        $state = $limiter->record($state, $this->moment(10), false);

        self::assertSame(10, $state->cumulativeWaitSeconds);
    }

    /**
     * A penalty cannot push the wait beyond `budget × step`, which the window is guaranteed to exceed.
     */
    public function testTheCumulativeWaitIsCappedBelowTheWindow(): void
    {
        $limiter = $this->limiter();
        $state = PaymentAttemptState::of(1, 30, $this->moment(0));

        self::assertTrue($limiter->decide($state, $this->moment(30))->isAllowed());

        $state = $limiter->record($state, $this->moment(30), true);

        self::assertSame(2, $state->attempts);
        self::assertSame(30, $state->cumulativeWaitSeconds);
    }

    public function testAnExpiredStateStartsAgainFromNothing(): void
    {
        $limiter = $this->limiter();
        $expired = PaymentAttemptState::of(3, 30, $this->moment(0));

        self::assertTrue($limiter->decide($expired, $this->moment(100))->isAllowed());

        $fresh = $limiter->record($expired, $this->moment(100), false);

        self::assertSame(1, $fresh->attempts);
        self::assertSame(0, $fresh->cumulativeWaitSeconds);
    }

    /**
     * A retry during the wait, penalised or not, neither moves the last attempt nor adds a step: the wait ends
     * when it was going to.
     */
    public function testAnAttemptDuringAWaitDoesNotPushTheRetryBack(): void
    {
        $limiter = $this->limiter();
        $state = $limiter->record(null, $this->moment(0), true);

        $retried = $limiter->record($state, $this->moment(5), true);
        $retried = $limiter->record($retried, $this->moment(9), false);

        self::assertSame($state, $retried);
        self::assertEquals($this->moment(10), $limiter->decide($retried, $this->moment(9))->retryAt);
        self::assertTrue($limiter->decide($retried, $this->moment(10))->isAllowed());
    }

    public function testASpentBudgetIsNotExtendedByAnAttemptDuringItsWait(): void
    {
        $limiter = $this->limiter();
        $state = null;

        foreach ([0, 1, 2] as $second) {
            $state = $limiter->record($state, $this->moment($second), false);
        }

        self::assertNotNull($state);
        $retried = $limiter->record($state, $this->moment(50), false);

        self::assertSame($state, $retried);
        self::assertEquals($this->moment(2 + 100), $limiter->decide($retried, $this->moment(50))->retryAt);
    }

    public function testTheTimeToLiveIsTheWindow(): void
    {
        self::assertSame(100, $this->limiter()->timeToLiveSeconds());
    }

    /**
     * Budget 3, step 10 s, window 100 s.
     */
    private function limiter(): PaymentAttemptLimiter
    {
        return new PaymentAttemptLimiter(PaymentAttemptPolicy::of(3, 10, 100));
    }

    private function moment(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable(self::START)->modify(\sprintf('+%d seconds', $seconds));
    }
}
