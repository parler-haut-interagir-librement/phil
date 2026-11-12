<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Domain;

use Attribute;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;
use Phil\RateLimitBundle\Domain\Exception\UnknownLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(RateLimit::class)]
#[CoversClass(InvalidRateLimit::class)]
#[CoversClass(UnknownLimiter::class)]
final class RateLimitTest extends TestCase
{
    public function testARuleNamesItsLimiterAndMayNameItsAmountAndKey(): void
    {
        $rule = new RateLimit('api', 2, 'user');

        self::assertSame(['api', 2, 'user'], [$rule->limiter, $rule->amount, $rule->key]);
        self::assertSame([null, null], [new RateLimit('api')->amount, new RateLimit('api')->key]);
        self::assertSame(1, new RateLimit('api', 1)->amount);
    }

    public function testItGoesOnClassesAndMethodsAndRepeats(): void
    {
        $attribute = new ReflectionClass(RateLimit::class)->getAttributes(Attribute::class)[0]->newInstance();

        self::assertSame(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE, $attribute->flags);
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $this->expectExceptionObject(InvalidRateLimit::because('A rate limit names its limiter.'));

        new RateLimit('');
    }

    public function testAnAmountBelowOneIsRefused(): void
    {
        $this->expectExceptionObject(InvalidRateLimit::because('A rate limit consumes one or more; 0 is not.'));

        new RateLimit('api', 0);
    }

    public function testAnEmptyKeyIsRefused(): void
    {
        $this->expectExceptionObject(InvalidRateLimit::because("A rate limit names its key strategy, or leaves it null for the limiter's own."));

        new RateLimit('api', key: '');
    }

    public function testAnUnknownNameListsWhatIsDeclared(): void
    {
        self::assertSame('No rate limiter is named "x"; the limiters declared are "a", "b".', UnknownLimiter::named('x', ['a', 'b'])->getMessage());
        self::assertSame('No rate limiter is named "x"; the limiters declared are none.', UnknownLimiter::named('x', [])->getMessage());
        self::assertSame('No key strategy is named "x"; the strategies declared are "ip".', UnknownLimiter::key('x', ['ip'])->getMessage());
    }
}
