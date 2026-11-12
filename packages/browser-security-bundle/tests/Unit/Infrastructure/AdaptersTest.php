<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use Phil\BrowserSecurityBundle\Infrastructure\Cache\CacheDeduplicator;
use Phil\BrowserSecurityBundle\Infrastructure\IpAnonymizer\TruncatedClientAddress;
use Phil\BrowserSecurityBundle\Infrastructure\RateLimiter\RateLimiterThrottle;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(CacheDeduplicator::class)]
#[CoversClass(TruncatedClientAddress::class)]
#[CoversClass(RateLimiterThrottle::class)]
final class AdaptersTest extends TestCase
{
    public function testTheDeduplicatorRemembersAFingerprintForItsWindow(): void
    {
        $pool = new ArrayAdapter();
        $deduplicator = new CacheDeduplicator($pool, 60);

        self::assertTrue($deduplicator->isNew('abc'));
        self::assertFalse($deduplicator->isNew('abc'));
        self::assertTrue($deduplicator->isNew('def'));
        self::assertTrue($pool->getItem('phil_browser_security.abc')->isHit());
        self::assertSame(3600, CacheDeduplicator::DEFAULT_WINDOW);
    }

    public function testTheWindowOfTheDeduplicator(): void
    {
        $pool = new ArrayAdapter();
        new CacheDeduplicator($pool, 60)->isNew('abc');
        $expiries = $pool->getValues();

        self::assertSame(['phil_browser_security.abc'], array_keys($expiries));
        $expiry = new ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($pool);
        self::assertIsArray($expiry);
        self::assertEqualsWithDelta(microtime(true) + 60, $expiry['phil_browser_security.abc'], 2);
    }

    public function testTheAddressIsTruncated(): void
    {
        $anonymizer = new TruncatedClientAddress(new Truncation());

        self::assertSame('192.0.2.0', $anonymizer->anonymize('192.0.2.123'));
        self::assertSame('2001:db8:1234::', $anonymizer->anonymize('2001:db8:1234:5678::1'));
        self::assertNull($anonymizer->anonymize('not an address'));
    }

    public function testTheThrottleCountsEachClientApart(): void
    {
        $throttle = new RateLimiterThrottle(new RateLimiterFactory(['id' => 'reports', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage()));

        self::assertTrue($throttle->allow('a'));
        self::assertTrue($throttle->allow('a'));
        self::assertFalse($throttle->allow('a'));
        self::assertTrue($throttle->allow('b'));
    }
}
