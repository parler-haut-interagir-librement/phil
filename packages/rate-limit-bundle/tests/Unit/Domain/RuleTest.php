<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Domain;

use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;
use Phil\RateLimitBundle\Domain\Exception\UnknownLimiter;
use Phil\RateLimitBundle\Domain\ValueObject\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rule::class)]
#[CoversClass(InvalidRateLimit::class)]
#[CoversClass(UnknownLimiter::class)]
final class RuleTest extends TestCase
{
    public function testARuleNamesItsLimiterAndMayNameItsAmountAndKey(): void
    {
        $rule = new Rule('api', 2, 'user');

        self::assertSame(['api', 2, 'user'], [$rule->limiter, $rule->amount, $rule->key]);
        self::assertSame([null, null], [new Rule('api')->amount, new Rule('api')->key]);
        self::assertSame(1, new Rule('api', 1)->amount);
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $this->expectExceptionObject(InvalidRateLimit::because('A rate limit names its limiter.'));

        new Rule('');
    }

    public function testAnAmountBelowOneIsRefused(): void
    {
        $this->expectExceptionObject(InvalidRateLimit::because('A rate limit consumes one or more; 0 is not.'));

        new Rule('api', 0);
    }

    public function testAnEmptyKeyIsRefused(): void
    {
        $this->expectExceptionObject(InvalidRateLimit::because("A rate limit names its key strategy, or leaves it null for the limiter's own."));

        new Rule('api', key: '');
    }

    public function testAnUnknownNameListsWhatIsDeclared(): void
    {
        self::assertSame('No rate limiter is named "x"; the limiters declared are "a", "b".', UnknownLimiter::named('x', ['a', 'b'])->getMessage());
        self::assertSame('No rate limiter is named "x"; the limiters declared are none.', UnknownLimiter::named('x', [])->getMessage());
        self::assertSame('No key strategy is named "x"; the strategies declared are "ip".', UnknownLimiter::key('x', ['ip'])->getMessage());
    }
}
