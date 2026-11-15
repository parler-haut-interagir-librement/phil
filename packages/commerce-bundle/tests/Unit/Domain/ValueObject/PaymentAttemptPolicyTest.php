<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentAttemptPolicy::class)]
final class PaymentAttemptPolicyTest extends TestCase
{
    public function testAWindowJustAboveTheLongestCumulativeWaitIsAccepted(): void
    {
        $policy = PaymentAttemptPolicy::of(5, 60, 301);

        self::assertSame(300, $policy->longestCumulativeWaitSeconds());
        self::assertSame(301, $policy->windowSeconds);
    }

    /**
     * A window equal to the longest cumulative wait lets the state expire at the very moment its last
     * penalty would have ended, which is a penalty that never slows anything down.
     */
    public function testTheSmallestBudgetAndStepAreAccepted(): void
    {
        $policy = PaymentAttemptPolicy::of(1, 1, 2);

        self::assertSame([1, 1, 2], [$policy->budget, $policy->penaltyStepSeconds, $policy->windowSeconds]);
    }

    public function testTheLargestProductInANativeIntegerIsAccepted(): void
    {
        $budget = intdiv(\PHP_INT_MAX, 2);

        self::assertSame($budget * 2, PaymentAttemptPolicy::of($budget, 2, \PHP_INT_MAX)->longestCumulativeWaitSeconds());
    }

    public function testAWindowEqualToTheLongestCumulativeWaitIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The window (300 s) must exceed the longest cumulative wait, budget × step (300 s).');

        PaymentAttemptPolicy::of(5, 60, 300);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function invalidPolicies(): iterable
    {
        yield 'no budget' => [0, 60, 3_600];
        yield 'no step' => [5, 0, 3_600];
        yield 'negative step' => [5, -1, 3_600];
        yield 'overflowing product' => [\PHP_INT_MAX, 2, \PHP_INT_MAX];
    }

    #[DataProvider('invalidPolicies')]
    public function testAnInvalidPolicyIsRefused(int $budget, int $step, int $window): void
    {
        $this->expectException(InvalidValue::class);

        PaymentAttemptPolicy::of($budget, $step, $window);
    }
}
