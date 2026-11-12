<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Application;

use Phil\RateLimitBundle\Application\RateLimitRules;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitRules::class)]
final class RateLimitRulesTest extends TestCase
{
    public function testNullIsNoRule(): void
    {
        self::assertSame([], RateLimitRules::from(null));
        self::assertSame([], RateLimitRules::from([]));
    }

    public function testOneRule(): void
    {
        $rule = new RateLimit('api');

        self::assertEquals([new RateLimit('api')], RateLimitRules::from('api'));
        self::assertSame([$rule], RateLimitRules::from($rule));
        self::assertEquals([new RateLimit('api', 2, 'user')], RateLimitRules::from(['limiter' => 'api', 'amount' => 2, 'key' => 'user']));
        self::assertEquals([new RateLimit('api')], RateLimitRules::from(['limiter' => 'api', 'amount' => null, 'key' => null]));
    }

    public function testAListOfRules(): void
    {
        self::assertEquals(
            [new RateLimit('api'), new RateLimit('search', key: 'ip_route'), new RateLimit('x', 3)],
            RateLimitRules::from(['api', ['limiter' => 'search', 'key' => 'ip_route'], new RateLimit('x', 3)]),
        );
        self::assertCount(16, RateLimitRules::from(array_fill(0, 16, 'api')));
        self::assertSame(16, RateLimitRules::MAX_RULES);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalid(): iterable
    {
        $list = 'A list of rate limits holds at most 16 rules, one after the other.';
        $rule = 'A rate limit is a limiter\'s name, or a map of "limiter", an integer "amount" and a string "key".';

        yield 'too many' => [array_fill(0, 17, 'api'), $list];
        yield 'a map without limiter' => [['amount' => 2], $list];
        yield 'an integer' => [3, $rule];
        yield 'a list of integers' => [[3], $rule];
        yield 'a limiter not a string' => [['limiter' => 3], $rule];
        yield 'an unknown key' => [['limiter' => 'api', 'window' => 3], $rule];
        yield 'an amount not an integer' => [['limiter' => 'api', 'amount' => '2'], $rule];
        yield 'a key not a string' => [['limiter' => 'api', 'key' => 2], $rule];
        yield 'a nested list' => [[['api']], $rule];
    }

    #[DataProvider('invalid')]
    public function testOtherShapesAreRefused(mixed $value, string $message): void
    {
        $this->expectExceptionObject(InvalidRateLimit::because($message));

        RateLimitRules::from($value);
    }
}
