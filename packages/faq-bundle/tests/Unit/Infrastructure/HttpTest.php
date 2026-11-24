<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Infrastructure;

use LogicException;
use Override;
use Phil\FaqBundle\Application\Exception\TooManyRequests;
use Phil\FaqBundle\Infrastructure\Http\AdminGuard;
use Phil\FaqBundle\Infrastructure\Http\FaqGuard;
use Phil\FaqBundle\Infrastructure\RateLimiter\SymfonyThrottle;
use Phil\FaqBundle\Infrastructure\Twig\KitComponents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Reservation;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(FaqGuard::class)]
#[CoversClass(AdminGuard::class)]
#[CoversClass(SymfonyThrottle::class)]
#[CoversClass(KitComponents::class)]
final class HttpTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paths(): iterable
    {
        yield 'a path' => ['/faq?q=pay', '/faq?q=pay'];
        yield 'the root' => ['/', '/'];
        yield 'empty' => ['', '/fallback'];
        yield 'another site' => ['https://evil.example/', '/fallback'];
        yield 'a protocol-relative address' => ['//evil.example/', '/fallback'];
        yield 'a backslash' => ['/\\evil.example/', '/fallback'];
        yield 'a backslash further on' => ['/faq\\x', '/fallback'];
        yield 'a space' => ['/faq x', '/fallback'];
        yield 'a line break' => ["/faq\nLocation: x", '/fallback'];
        yield 'a delete character' => ["/faq\x7F", '/fallback'];
        yield 'relative' => ['faq', '/fallback'];
        yield 'too long' => ['/'.str_repeat('a', 2_000), '/fallback'];
        yield 'the longest' => ['/'.str_repeat('a', 1_999), '/'.str_repeat('a', 1_999)];
    }

    #[DataProvider('paths')]
    public function testAFormComesBackToAPathOfThisSiteOnly(string $path, string $expected): void
    {
        self::assertSame($expected, FaqGuard::localPath($path, '/fallback'));
    }

    public function testAParameterAndAFragmentAreAdded(): void
    {
        self::assertSame('/faq?voted=how-to#how-to', FaqGuard::withParameter('/faq#old', 'voted', 'how-to', 'how-to'));
        self::assertSame('/faq?voted=x#x', FaqGuard::withParameter('/faq#a#b', 'voted', 'x', 'x'));
        self::assertSame('/a//b', FaqGuard::localPath('/a//b', '/fallback'));
        self::assertSame('/faq?q=a%20b&voted=x#x', FaqGuard::withParameter('/faq?q=a%20b', 'voted', 'x', 'x'));
    }

    public function testTheAdministrationEditsTheConfiguredLanguages(): void
    {
        self::assertSame(['fr_CA', 'en'], AdminGuard::locales(['fr-CA', 'en', 'fr_CA']));
        self::assertSame(['en'], AdminGuard::locales([]));
        self::assertSame(['en', 'fr'], AdminGuard::locales(['en', 'en', 'fr']));
    }

    public function testAThrottleRefusesOnceTheKeyHasUsedItsShare(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00 UTC');
        $factory = new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
        $throttle = new SymfonyThrottle($factory, $clock);

        $throttle->consume('q1');
        $throttle->consume('q2');

        try {
            $throttle->consume('q1');
            self::fail('The share is used.');
        } catch (TooManyRequests $tooManyRequests) {
            self::assertGreaterThanOrEqual(1, $tooManyRequests->retryAfter);
        }
    }

    public function testTheRecipesAreNamedAfterTheSubdirectory(): void
    {
        self::assertSame('Button', new KitComponents()->name('Button'));
        self::assertSame('ui:Button', new KitComponents('/ui/')->name('Button'));
        self::assertSame('ui:kit:Button', new KitComponents('ui/kit')->name('Button'));
    }

    public function testAThrottleWaitsAtLeastASecond(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00 UTC');
        $limiter = new class($clock) implements LimiterInterface {
            public function __construct(
                private readonly MockClock $clock,
            ) {
            }

            #[Override]
            public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
            {
                throw new LogicException('Not reserved.');
            }

            #[Override]
            public function consume(int $tokens = 1): RateLimit
            {
                return new RateLimit(0, $this->clock->now()->modify('+'.$this->seconds.' seconds'), false, 1);
            }

            #[Override]
            public function reset(): void
            {
            }

            public int $seconds = 0;
        };
        $factory = new readonly class($limiter) implements RateLimiterFactoryInterface {
            public function __construct(
                private LimiterInterface $limiter,
            ) {
            }

            #[Override]
            public function create(?string $key = null): LimiterInterface
            {
                return $this->limiter;
            }
        };
        $throttle = new SymfonyThrottle($factory, $clock);

        foreach ([0 => 1, 1 => 1, 2 => 2, 90 => 90] as $seconds => $retryAfter) {
            $limiter->seconds = $seconds;

            try {
                $throttle->consume('q1');
                self::fail('The share is used.');
            } catch (TooManyRequests $tooManyRequests) {
                self::assertSame($retryAfter, $tooManyRequests->retryAfter, (string) $seconds);
            }
        }
    }
}
