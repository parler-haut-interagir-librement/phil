<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Phil\NewsletterBundle\Application\Sending\SenderProfile;
use Phil\NewsletterBundle\Infrastructure\RateLimiter\RateLimiterSendThrottle;
use Phil\NewsletterBundle\Infrastructure\Site\ConfiguredSite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(RateLimiterSendThrottle::class)]
#[CoversClass(ConfiguredSite::class)]
final class ThrottleTest extends TestCase
{
    public function testEachDomainHasItsOwnPace(): void
    {
        $throttle = new RateLimiterSendThrottle(new InMemoryStorage(), new NativeClock());
        $profile = new SenderProfile('default', 'a@example.com', rate: 2, domainRates: ['gmail.com' => 1]);

        self::assertSame(0, $throttle->wait($profile, 'example.com'));
        self::assertSame(0, $throttle->wait($profile, 'EXAMPLE.com'));
        $wait = $throttle->wait($profile, 'example.com');
        self::assertGreaterThanOrEqual(1, $wait);
        self::assertLessThanOrEqual(61, $wait);
        self::assertSame(0, $throttle->wait($profile, 'example.org'));
        self::assertSame(0, $throttle->wait($profile, 'gmail.com'));
        self::assertGreaterThanOrEqual(1, $throttle->wait($profile, 'gmail.com'));
        self::assertSame(0, $throttle->wait(new SenderProfile('other', 'a@example.com', rate: 2), 'example.com'));
    }

    public function testTheDailyCeilingOfAProfile(): void
    {
        $throttle = new RateLimiterSendThrottle(new InMemoryStorage(), new NativeClock());
        $profile = new SenderProfile('default', 'a@example.com', dailyCap: 2);

        self::assertSame(0, $throttle->wait($profile, 'a.example'));
        self::assertSame(0, $throttle->wait($profile, 'b.example'));
        $wait = $throttle->wait($profile, 'c.example');
        self::assertGreaterThan(80_000, $wait);
        self::assertLessThanOrEqual(86_401, $wait);
    }

    public function testTheConfiguredSite(): void
    {
        self::assertSame('default', new ConfiguredSite()->site()->key);
        self::assertSame('shop', new ConfiguredSite('shop')->site()->key);
    }

    public function testAProfileAndADomainAreNotConfusedWithAnotherPairOfTheSameLetters(): void
    {
        $throttle = new RateLimiterSendThrottle(new InMemoryStorage(), new NativeClock());

        self::assertSame(0, $throttle->wait(new SenderProfile('a', 'a@example.com', rate: 1), 'bc'));
        self::assertSame(0, $throttle->wait(new SenderProfile('ab', 'a@example.com', rate: 1), 'c'));
    }

    public function testTheWaitIsOneSecondAtLeast(): void
    {
        $throttle = new RateLimiterSendThrottle(new InMemoryStorage(), new MockClock('+1 day'));
        $profile = new SenderProfile('default', 'a@example.com', rate: 1);

        self::assertSame(0, $throttle->wait($profile, 'example.com'));
        self::assertSame(1, $throttle->wait($profile, 'example.com'));
    }
}
