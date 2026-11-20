<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Enforcers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Limiters that count one request together, on the limiters of `symfony/rate-limiter` themselves: a compound limiter,
 * and several rules on one route. The request passes only when every one of them accepts it — the `CompoundLimiter`
 * of Symfony accepted a request one of its limiters refused before 8.2.0-BETA2.
 */
#[CoversClass(SymfonyLimiter::class)]
#[CoversClass(RateLimitEnforcer::class)]
final class CombinedLimitersTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function policies(): iterable
    {
        $window = ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'];
        $sliding = ['policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute'];
        $bucket = ['policy' => 'token_bucket', 'limit' => 2, 'rate' => ['interval' => '1 minute', 'amount' => 1]];

        yield 'a fixed window and a sliding window' => [$window, $sliding];
        yield 'a fixed window and a token bucket' => [$window, $bucket];
        yield 'a sliding window and a fixed window' => [$sliding, $window + ['limit' => 2]];
    }

    /**
     * @param array<string, mixed> $wide
     * @param array<string, mixed> $tight
     */
    #[DataProvider('policies')]
    public function testACompoundLimiterRefusesWhenOneOfItsLimitersDoes(array $wide, array $tight): void
    {
        $storage = new InMemoryStorage();
        $compound = new SymfonyLimiter(new CompoundRateLimiterFactory([
            new RateLimiterFactory(['id' => 'wide'] + $wide, $storage),
            new RateLimiterFactory(['id' => 'tight'] + $tight, $storage),
        ]));

        $accepted = [];

        for ($hit = 0; $hit < 4; ++$hit) {
            $accepted[] = $compound->consume('alice', 1, Request::create('/'))->accepted;
        }

        self::assertSame([true, true, false, false], $accepted);
        self::assertTrue($compound->consume('bob', 1, Request::create('/'))->accepted, 'another key counts apart');
    }

    /**
     * Two rules on one route: the tighter refuses the third request even though the wider accepts it, whichever comes
     * first.
     */
    public function testSeveralRulesRefuseWhenOneOfThemDoes(): void
    {
        foreach ([['wide', 'tight'], ['tight', 'wide']] as $order) {
            $storage = new InMemoryStorage();
            $enforcer = Enforcers::of([
                'wide' => new SymfonyLimiter(new RateLimiterFactory(['id' => 'wide', 'policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 minute'], $storage), 60),
                'tight' => new SymfonyLimiter(new RateLimiterFactory(['id' => 'tight', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 minute'], $storage), 60),
            ]);
            $rules = array_map(static fn (string $limiter): RateLimit => new RateLimit($limiter), $order);
            $outcomes = [];

            for ($hit = 0; $hit < 3; ++$hit) {
                try {
                    $outcomes[] = $enforcer->enforce($this->request(), $rules)?->limiter;
                } catch (TooManyRequests $tooManyRequests) {
                    $outcomes[] = 'refused by '.$tooManyRequests->decision->limiter;
                }
            }

            self::assertSame(['tight', 'tight', 'refused by tight'], $outcomes, implode(', ', $order));
        }
    }

    private function request(): Request
    {
        $request = Request::create('/');
        $request->headers->set('X-Account', 'alice');

        return $request;
    }
}
