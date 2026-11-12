<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Application;

use Phil\RateLimitBundle\Application\RateLimitHeaders;
use Phil\RateLimitBundle\Domain\HeaderStyle;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitHeaders::class)]
final class RateLimitHeadersTest extends TestCase
{
    public function testTheDraftFieldsNameThePolicyAfterTheLimiter(): void
    {
        $decision = Decision::accepted(100, 42, 17, 60)->named('api');

        self::assertSame(['RateLimit-Policy' => '"api";q=100;w=60', 'RateLimit' => '"api";r=42;t=17'], new RateLimitHeaders()->of($decision));
        self::assertSame(['RateLimit-Policy' => '"api";q=100', 'RateLimit' => '"api";r=42;t=17'], new RateLimitHeaders(HeaderStyle::Draft)->of(Decision::accepted(100, 42, 17)->named('api')));
    }

    public function testTheLegacyFieldsAreThree(): void
    {
        self::assertSame(['RateLimit-Limit' => '100', 'RateLimit-Remaining' => '0', 'RateLimit-Reset' => '17'], new RateLimitHeaders(HeaderStyle::Legacy)->of(Decision::refused(100, 0, 17, 5)->named('api')));
    }

    public function testBothOrNone(): void
    {
        $decision = Decision::accepted(3, 2, 1, 60)->named('a');

        self::assertSame(['RateLimit-Policy', 'RateLimit', 'RateLimit-Limit', 'RateLimit-Remaining', 'RateLimit-Reset'], array_keys(new RateLimitHeaders(HeaderStyle::Both)->of($decision)));
        self::assertSame([], new RateLimitHeaders(HeaderStyle::None)->of($decision));
    }

    public function testADecisionWithoutALimitWritesNone(): void
    {
        self::assertSame([], new RateLimitHeaders(HeaderStyle::Both)->of(Decision::accepted(null, 0, 0)->named('free')));
    }
}
