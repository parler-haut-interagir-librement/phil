<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\BrowserSecurityBundle\Infrastructure\RateLimit\LimiterReportThrottle;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(LimiterReportThrottle::class)]
final class BridgesTest extends TestCase
{
    public function testTheThrottleConsumesOneForTheDigest(): void
    {
        $request = new Request();
        $limiter = $this->limiter(true);

        self::assertTrue(new LimiterReportThrottle($limiter, new RequestStack([$request]))->allow('digest'));
        self::assertSame([['digest', 1, $request]], $limiter->consumed);
    }

    public function testTheThrottleRefusesWhatTheLimiterRefuses(): void
    {
        $limiter = $this->limiter(false);

        self::assertFalse(new LimiterReportThrottle($limiter, new RequestStack())->allow('digest'));
        self::assertInstanceOf(Request::class, $limiter->consumed[0][2], 'a limiter always gets a request');
    }

    /**
     * @return Limiter&object{consumed: list<array{string, int, Request}>}
     */
    private function limiter(bool $accepted): Limiter
    {
        return new class($accepted) implements Limiter {
            /** @var list<array{string, int, Request}> */
            public array $consumed = [];

            public function __construct(private readonly bool $accepted)
            {
            }

            #[Override]
            public function consume(string $key, int $amount, Request $request): Decision
            {
                $this->consumed[] = [$key, $amount, $request];

                return new Decision($this->accepted, 1, 0, 1);
            }
        };
    }
}
