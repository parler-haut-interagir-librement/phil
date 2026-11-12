<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Application;

use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Domain\Exception\UnknownLimiter;
use Phil\RateLimitBundle\Tests\Unit\Fixture\HeaderKey;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Locator;
use Phil\RateLimitBundle\Tests\Unit\Fixture\ScriptedLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Limiters::class)]
#[CoversClass(KeyResolvers::class)]
final class LimitersTest extends TestCase
{
    public function testALimiterAndItsDefaults(): void
    {
        $api = new ScriptedLimiter();
        $limiters = new Limiters(new Locator(['api' => $api]), ['api' => ['key' => 'user', 'amount' => 2]]);

        self::assertSame($api, $limiters->get('api'));
        self::assertSame('user', $limiters->keyOf('api'));
        self::assertSame(2, $limiters->amountOf('api'));
        self::assertFalse($limiters->observes('api'), 'A limiter enforces unless it says otherwise.');
    }

    public function testALimiterInObservation(): void
    {
        $limiters = new Limiters(new Locator([]), ['watched' => ['key' => 'ip', 'amount' => 1, 'observe' => true], 'api' => ['key' => 'ip', 'amount' => 1, 'observe' => false]]);

        self::assertTrue($limiters->observes('watched'));
        self::assertFalse($limiters->observes('api'));
    }

    public function testEveryReadOfAnUnknownLimiterFails(): void
    {
        $limiters = new Limiters(new Locator([]), ['api' => ['key' => 'ip', 'amount' => 1]]);

        foreach ([$limiters->get(...), $limiters->keyOf(...), $limiters->amountOf(...), $limiters->observes(...)] as $read) {
            try {
                $read('nope');
                self::fail('An unknown limiter is refused.');
            } catch (UnknownLimiter $unknownLimiter) {
                self::assertSame('No rate limiter is named "nope"; the limiters declared are "api".', $unknownLimiter->getMessage());
            }
        }
    }

    public function testAStrategyByName(): void
    {
        $key = new HeaderKey();
        $keys = new KeyResolvers(new Locator(['ip' => $key]), ['ip']);

        self::assertSame($key, $keys->get('ip'));

        $this->expectExceptionObject(UnknownLimiter::key('user', ['ip']));
        $keys->get('user');
    }
}
