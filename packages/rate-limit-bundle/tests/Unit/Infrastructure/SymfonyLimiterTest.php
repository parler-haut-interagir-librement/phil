<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(SymfonyLimiter::class)]
final class SymfonyLimiterTest extends TestCase
{
    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-03-14 10:30:15.250', 'UTC');
    }

    public function testAnAcceptedHitResetsWhenTheLimiterSays(): void
    {
        $limit = new RateLimit(7, $this->at(0), true, 10, $this->at(12.2));

        self::assertEquals(new Decision(true, 10, 7, 13, 0, 60), $this->limiter($limit, 60)->consume('k', 1, Request::create('/')));
    }

    public function testWithoutResetTheWindowBoundsIt(): void
    {
        $limit = new RateLimit(7, $this->at(0), true, 10);

        self::assertEquals(new Decision(true, 10, 7, 60, 0, 60), $this->limiter($limit, 60)->consume('k', 1, Request::create('/')));
        self::assertEquals(new Decision(true, 10, 7, 0, 0), $this->limiter($limit, null)->consume('k', 1, Request::create('/')));
    }

    public function testARefusalWaitsUntilTheNextHitRoundedUp(): void
    {
        $refused = new RateLimit(0, $this->at(4.1), false, 10);

        self::assertEquals(new Decision(false, 10, 0, 5, 5, 60), $this->limiter($refused, 60)->consume('k', 1, Request::create('/')));

        $withReset = new RateLimit(0, $this->at(4), false, 10, $this->at(30));

        self::assertEquals(new Decision(false, 10, 0, 30, 4), $this->limiter($withReset, null)->consume('k', 1, Request::create('/')));

        $past = new RateLimit(0, $this->at(-3), false, 10, $this->at(-1));

        self::assertEquals(new Decision(false, 10, 0, 0, 0), $this->limiter($past, null)->consume('k', 1, Request::create('/')));
    }

    public function testNoLimitWritesNoHeader(): void
    {
        $factory = new RateLimiterFactory(['id' => 'x', 'policy' => 'no_limit'], new InMemoryStorage());

        self::assertNull(new SymfonyLimiter($factory)->consume('k', 1, Request::create('/'))->limit);
    }

    public function testARealWindowCountsByKeyOnTheSystemClock(): void
    {
        $factory = new RateLimiterFactory(['id' => 'x', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage());
        $limiter = new SymfonyLimiter($factory, 60);

        self::assertSame(1, $limiter->consume('a', 1, Request::create('/'))->remaining);
        self::assertSame(0, $limiter->consume('a', 1, Request::create('/'))->remaining);
        self::assertSame(1, $limiter->consume('b', 1, Request::create('/'))->remaining);

        $refused = $limiter->consume('a', 1, Request::create('/'));

        self::assertFalse($refused->accepted);
        self::assertGreaterThan(0, $refused->retryAfter);
        self::assertLessThanOrEqual(61, $refused->retryAfter);
    }

    private function at(float $seconds): DateTimeImmutable
    {
        $moment = DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', (float) $this->clock->now()->format('U.u') + $seconds));
        self::assertInstanceOf(DateTimeImmutable::class, $moment);

        return $moment;
    }

    private function limiter(RateLimit $limit, ?int $window): SymfonyLimiter
    {
        $factory = new readonly class($limit) implements RateLimiterFactoryInterface {
            public function __construct(
                private RateLimit $limit,
            ) {
            }

            #[Override]
            public function create(?string $key = null): LimiterInterface
            {
                return new readonly class($this->limit) implements LimiterInterface {
                    public function __construct(
                        private RateLimit $limit,
                    ) {
                    }

                    #[Override]
                    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
                    {
                        return new Reservation(0.0, $this->limit);
                    }

                    #[Override]
                    public function consume(int $tokens = 1): RateLimit
                    {
                        return $this->limit;
                    }

                    #[Override]
                    public function reset(): void
                    {
                    }
                };
            }
        };

        return new SymfonyLimiter($factory, $window, $this->clock);
    }
}
