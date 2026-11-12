<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\RateLimiter;

use Override;
use Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Infrastructure\RateLimiter\SymfonyCollectionRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(SymfonyCollectionRateLimiter::class)]
final class SymfonyCollectionRateLimiterTest extends TestCase
{
    public function testEachSiteAndDigestHasItsOwnBudget(): void
    {
        $limiter = new SymfonyCollectionRateLimiter(new RateLimiterFactory(['id' => 't', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 hour'], new InMemoryStorage()));
        $site = SiteId::fromString('s');
        $digest = ClientDigest::fromString('86400-1:'.str_repeat('a', 32));

        self::assertTrue($limiter->consume($site, $digest));
        self::assertTrue($limiter->consume($site, $digest));
        self::assertFalse($limiter->consume($site, $digest));
        self::assertTrue($limiter->consume(SiteId::fromString('other'), $digest));
        self::assertTrue($limiter->consume($site, ClientDigest::fromString('86400-1:'.str_repeat('b', 32))));
    }

    public function testTheKeyHoldsNeitherTheSiteNorTheDigestInClear(): void
    {
        $factory = new class implements RateLimiterFactoryInterface {
            public ?string $key = null;

            #[Override]
            public function create(?string $key = null): LimiterInterface
            {
                $this->key = $key;

                throw new RuntimeException('unused');
            }
        };

        new SymfonyCollectionRateLimiter($factory)->consume(SiteId::fromString('spring-con'), ClientDigest::fromString('86400-1:'.str_repeat('a', 32)));

        self::assertSame(hash('xxh128', "spring-con\x0086400-1:".str_repeat('a', 32)), $factory->key);
    }

    public function testAFailingLimiterRefuses(): void
    {
        $factory = new class implements RateLimiterFactoryInterface {
            #[Override]
            public function create(?string $key = null): LimiterInterface
            {
                throw new RuntimeException('The storage is down.');
            }
        };

        self::assertFalse(new SymfonyCollectionRateLimiter($factory)->consume(SiteId::fromString('s'), ClientDigest::fromString('86400-1:'.str_repeat('a', 32))));
    }
}
