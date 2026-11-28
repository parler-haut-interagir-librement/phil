<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Benchmark;

use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Application\RateLimitHeaders;
use Phil\RateLimitBundle\Domain\ValueObject\Rule;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitListener;
use Phil\RateLimitBundle\Infrastructure\Key\ClientIpKey;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\AttributeLimiterFactory;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\AttributeLimits;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Locator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * What the bundle adds to every request: a route without a rule, one with a rule and three in its `_rate_limit`
 * default, and one consumption of Symfony's `#[RateLimit]` through the bundle, each over a sliding window of
 * symfony/rate-limiter on a memory storage — the cost of the bundle, the storage's own I/O aside —, and the headers
 * written on the response.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"rate-limit"})
 */
final class RateLimitListenerBench
{
    private RateLimitListener $listener;

    private HttpKernelInterface $kernel;

    private AttributeLimits $limits;

    private RequestStack $requests;

    public function __construct()
    {
        $this->setUp();
    }

    public function setUp(): void
    {
        $limiters = [];

        foreach (['a', 'b', 'c'] as $name) {
            $limiters[$name] = new SymfonyLimiter(new RateLimiterFactory(['id' => $name, 'policy' => 'sliding_window', 'limit' => \PHP_INT_MAX, 'interval' => '1 minute'], new InMemoryStorage()), 60);
        }

        $defaults = array_map(static fn (): array => ['key' => 'ip', 'amount' => 1], $limiters);
        $enforcer = new RateLimitEnforcer(new Limiters(new Locator($limiters), $defaults), new KeyResolvers(new Locator(['ip' => new ClientIpKey('a secret of the bench')]), ['ip']), new RateLimitHeaders());
        $this->listener = new RateLimitListener($enforcer);
        $this->requests = new RequestStack();
        $this->limits = new AttributeLimits($enforcer, new KeyResolvers(new Locator(['ip' => new ClientIpKey('a secret of the bench')]), ['ip']), $this->requests, 'a secret of the bench');
        $this->kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }

    public function benchWithoutRule(): void
    {
        $this->request([]);
    }

    public function benchOneRule(): void
    {
        $this->request([new Rule('a')]);
    }

    public function benchThreeRules(): void
    {
        $this->request([new Rule('a'), new Rule('b'), new Rule('c')]);
    }

    public function benchOneAttribute(): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.'.random_int(1, 254)]);
        $this->requests->push($request);
        new AttributeLimiterFactory($this->limits, 'a')->create(AttributeLimits::defaultKeyOf($request))->consume();
        $this->requests->pop();
        $this->listener->onKernelResponse(new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response()));
    }

    /**
     * @param list<Rule> $rules
     */
    private function request(array $rules): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.'.random_int(1, 254)]);

        if ([] !== $rules) {
            $request->attributes->set(RateLimitListener::ROUTE_DEFAULT, $rules);
        }

        $this->listener->onKernelController(new ControllerEvent($this->kernel, static fn (): Response => new Response(), $request, HttpKernelInterface::MAIN_REQUEST));
        $this->listener->onKernelResponse(new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Response()));
    }
}
