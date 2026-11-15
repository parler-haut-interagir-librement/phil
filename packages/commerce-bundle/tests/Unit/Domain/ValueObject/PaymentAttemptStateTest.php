<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptState;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

#[CoversClass(PaymentAttemptState::class)]
final class PaymentAttemptStateTest extends TestCase
{
    /**
     * The state is exactly three fields. A fourth — a verdict, a penalty counter — would be the one thing the
     * limiter must never keep.
     */
    public function testTheStateHoldsOnlyAttemptsCumulativeWaitAndLastAttempt(): void
    {
        $names = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            new ReflectionClass(PaymentAttemptState::class)->getProperties(),
        );

        self::assertSame(['attempts', 'cumulativeWaitSeconds', 'lastAttemptAt'], $names);
    }

    public function testItExpiresOneWindowAfterItsLastAttempt(): void
    {
        $policy = PaymentAttemptPolicy::of(3, 10, 60);
        $state = PaymentAttemptState::of(1, 0, new DateTimeImmutable('2026-01-01T12:00:00Z'));

        self::assertEquals(new DateTimeImmutable('2026-01-01T12:01:00Z'), $state->expiresAt($policy));
        self::assertFalse($state->isExpiredAt(new DateTimeImmutable('2026-01-01T12:00:59Z'), $policy));
        self::assertTrue($state->isExpiredAt(new DateTimeImmutable('2026-01-01T12:01:00Z'), $policy));
    }

    public function testTheNextAttemptWaitsForTheCumulativeWait(): void
    {
        $state = PaymentAttemptState::of(2, 20, new DateTimeImmutable('2026-01-01T12:00:00Z'));

        self::assertEquals(new DateTimeImmutable('2026-01-01T12:00:20Z'), $state->nextAttemptAllowedAt());
    }

    public function testAStateWithoutAttemptIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        PaymentAttemptState::of(0, 0, new DateTimeImmutable());
    }

    public function testANegativeWaitIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        PaymentAttemptState::of(1, -1, new DateTimeImmutable());
    }
}
