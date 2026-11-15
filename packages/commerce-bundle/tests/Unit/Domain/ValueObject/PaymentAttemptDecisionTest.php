<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptDecision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentAttemptDecision::class)]
final class PaymentAttemptDecisionTest extends TestCase
{
    public function testAnAllowedDecisionHasNoRetry(): void
    {
        $decision = PaymentAttemptDecision::allowed();

        self::assertTrue($decision->isAllowed());
        self::assertSame(0, $decision->retryAfterSeconds(new DateTimeImmutable()));
    }

    public function testTheRetryAfterIsRoundedUpToTheNextSecond(): void
    {
        $now = new DateTimeImmutable('2026-01-01T12:00:00.250000Z');
        $decision = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:10.000000Z'));

        self::assertFalse($decision->isAllowed());
        self::assertSame(10, $decision->retryAfterSeconds($now));
        self::assertSame(9, $decision->retryAfterSeconds(new DateTimeImmutable('2026-01-01T12:00:01Z')));
    }

    /**
     * One microsecond past a whole second is one more second to wait.
     */
    public function testAFractionOfASecondIsAWholeSecond(): void
    {
        $decision = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:10.000001Z'));

        self::assertSame(11, $decision->retryAfterSeconds(new DateTimeImmutable('2026-01-01T12:00:00Z')));
        self::assertSame(10, $decision->retryAfterSeconds(new DateTimeImmutable('2026-01-01T12:00:00.000001Z')));
        self::assertSame(1, $decision->retryAfterSeconds(new DateTimeImmutable('2026-01-01T12:00:10Z')));
    }

    public function testARetryAtNowIsZero(): void
    {
        $now = new DateTimeImmutable('2026-01-01T12:00:00.500000Z');

        self::assertSame(0, PaymentAttemptDecision::waitUntil($now)->retryAfterSeconds($now));
    }

    public function testARetryInThePastIsZero(): void
    {
        $decision = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:00Z'));

        self::assertSame(0, $decision->retryAfterSeconds(new DateTimeImmutable('2026-01-01T12:00:05Z')));
    }

    public function testTheLaterRetryWins(): void
    {
        $order = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:10Z'));
        $ip = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:30Z'));

        self::assertSame($ip, PaymentAttemptDecision::mostRestrictive($order, $ip));
        self::assertSame($ip, PaymentAttemptDecision::mostRestrictive($ip, $order));
    }

    public function testAnAllowanceAmongTheOthersIsSkipped(): void
    {
        $wait = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:10Z'));

        self::assertSame($wait, PaymentAttemptDecision::mostRestrictive(PaymentAttemptDecision::allowed(), PaymentAttemptDecision::allowed(), $wait));
    }

    public function testOnATieTheFirstDecisionIsKept(): void
    {
        $first = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:10Z'));
        $second = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:10Z'));

        self::assertSame($first, PaymentAttemptDecision::mostRestrictive($first, $second));
    }

    public function testAnyWaitBeatsAnAllowance(): void
    {
        $wait = PaymentAttemptDecision::waitUntil(new DateTimeImmutable('2026-01-01T12:00:10Z'));

        self::assertSame($wait, PaymentAttemptDecision::mostRestrictive(PaymentAttemptDecision::allowed(), $wait));
        self::assertSame($wait, PaymentAttemptDecision::mostRestrictive($wait, PaymentAttemptDecision::allowed()));
        self::assertTrue(PaymentAttemptDecision::mostRestrictive(PaymentAttemptDecision::allowed(), PaymentAttemptDecision::allowed())->isAllowed());
    }
}
