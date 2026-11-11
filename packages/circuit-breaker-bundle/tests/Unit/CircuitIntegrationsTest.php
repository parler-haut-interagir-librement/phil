<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\CircuitSnapshot;
use Phil\CircuitBreaker\CircuitState;
use Phil\CircuitBreakerBundle\Infrastructure\Cache\CacheCircuitStateStore;
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\CircuitBreakingHttpClient;
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception\CircuitOpen;
use Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitBreakerDataCollector;
use Phil\CircuitBreakerBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\CircuitBreakerBundle\Tests\Unit\Fixture\SwitchableOverride;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[CoversClass(CacheCircuitStateStore::class)]
#[CoversClass(CircuitBreakingHttpClient::class)]
#[CoversClass(CircuitOpen::class)]
#[CoversClass(CircuitBreakerDataCollector::class)]
final class CircuitIntegrationsTest extends TestCase
{
    private ArrayAdapter $pool;

    private FrozenClock $clock;

    private SwitchableOverride $override;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ArrayAdapter();
        $this->clock = new FrozenClock();
        $this->override = new SwitchableOverride();
    }

    public function testTheCacheKeepsEachCircuitsState(): void
    {
        $store = new CacheCircuitStateStore($this->pool);
        $payments = CircuitKey::of('http.payments');
        $at = new DateTimeImmutable('@1793793600');

        self::assertNull($store->load($payments));
        $store->save($payments, CircuitSnapshot::restore(CircuitState::HalfOpen, $at, 7, 3, 2, 4));
        $store->save(CircuitKey::of('http.search'), CircuitSnapshot::closed($at));

        $loaded = $store->load($payments);
        self::assertNotNull($loaded);
        self::assertSame([CircuitState::HalfOpen, 1_793_793_600, 7, 3, 2, 4], [$loaded->state, $loaded->since->getTimestamp(), $loaded->calls, $loaded->failures, $loaded->halfOpenSuccesses, $loaded->slowCalls]);
        self::assertSame(CircuitState::Closed, $store->load(CircuitKey::of('http.search'))?->state);
        self::assertTrue($this->pool->hasItem('phil_circuit_breaker.circuit.http.payments'));
        self::assertSame('phil_circuit_breaker.circuit.', CacheCircuitStateStore::PREFIX);
    }

    public function testAnEntryOfAnotherShapeReadsAsNone(): void
    {
        $store = new CacheCircuitStateStore($this->pool);
        $key = CircuitKey::of('http.payments');

        foreach ([
            'not an array' => 'open',
            'no state' => ['since' => 1],
            'unknown state' => ['state' => 'ajar', 'since' => 1],
            'state not a string' => ['state' => 1, 'since' => 1],
            'no since' => ['state' => 'open'],
            'since not a number' => ['state' => 'open', 'since' => '1', 'calls' => 0, 'failures' => 0, 'half_open_successes' => 0],
            'calls not a number' => ['state' => 'open', 'since' => 1, 'calls' => '0', 'failures' => 0, 'half_open_successes' => 0],
            'no failures' => ['state' => 'open', 'since' => 1, 'calls' => 0, 'half_open_successes' => 0, 'slow_calls' => 0],
            'no successes' => ['state' => 'open', 'since' => 1, 'calls' => 0, 'failures' => 0, 'slow_calls' => 0],
            'no slow calls' => ['state' => 'open', 'since' => 1, 'calls' => 0, 'failures' => 0, 'half_open_successes' => 0],
            'slow calls not a number' => ['state' => 'open', 'since' => 1, 'calls' => 0, 'failures' => 0, 'half_open_successes' => 0, 'slow_calls' => '0'],
            'an array keyed otherwise' => ['open', 1, 0, 0, 0],
        ] as $case => $value) {
            $this->pool->save($this->pool->getItem('phil_circuit_breaker.circuit.http.payments')->set($value));
            self::assertNull($store->load($key), $case);
        }

        $this->pool->save($this->pool->getItem('phil_circuit_breaker.circuit.http.payments')->set(['state' => 'open', 'since' => 5, 'calls' => 3, 'failures' => 1, 'half_open_successes' => 0, 'slow_calls' => 2]));
        $loaded = $store->load($key);
        self::assertSame([CircuitState::Open, 5, 3, 1, 0, 2], [$loaded?->state, $loaded?->since->getTimestamp(), $loaded?->calls, $loaded?->failures, $loaded?->halfOpenSuccesses, $loaded?->slowCalls]);
    }

    public function testAClientCountsServerErrorsAndOpens(): void
    {
        $answers = [new MockResponse('ok'), new MockResponse('missing', ['http_code' => 404]), new MockResponse('down', ['http_code' => 503]), new MockResponse('down', ['http_code' => 500])];
        $sent = 0;
        $inner = new MockHttpClient(static function () use (&$answers, &$sent): MockResponse {
            ++$sent;

            return array_shift($answers) ?? new MockResponse('ok');
        });
        $breakers = $this->breakers(CircuitPolicy::of(50, 4, 60, 30));
        $client = new CircuitBreakingHttpClient($inner, $breakers, 'http.payments');

        self::assertSame('ok', $client->request('GET', 'https://pay.example.com/a')->getContent());
        self::assertSame(404, $client->request('GET', 'https://pay.example.com/b')->getStatusCode());
        self::assertSame(503, $client->request('GET', 'https://pay.example.com/c')->getStatusCode());
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('http.payments'))->state());
        self::assertSame(500, $client->request('GET', 'https://pay.example.com/d')->getStatusCode());
        self::assertSame(CircuitState::Open, $breakers->get(CircuitKey::of('http.payments'))->state());

        try {
            $client->request('POST', 'https://pay.example.com/e');
            self::fail('A request went through an open circuit.');
        } catch (CircuitOpen $circuitOpen) {
            self::assertSame('The circuit "http.payments" is open: POST https://pay.example.com/e was not sent.', $circuitOpen->getMessage());
            self::assertInstanceOf(TransportExceptionInterface::class, $circuitOpen);
        }

        self::assertSame(4, $sent);
        $this->clock->advance(30);
        self::assertSame(200, $client->request('GET', 'https://pay.example.com/f')->getStatusCode());
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('http.payments'))->state());
    }

    /**
     * The transport's `total_time` is the duration of each request: two answers slower than 500 ms open the circuit.
     */
    public function testAClientMeasuresEachRequestAndCountsTheSlowOnes(): void
    {
        $answers = [new MockResponse('ok', ['total_time' => 0.5]), new MockResponse('ok', ['total_time' => 0.501]), new MockResponse('ok', ['total_time' => 2])];
        $inner = new MockHttpClient(static function () use (&$answers): MockResponse {
            return array_shift($answers) ?? new MockResponse('ok');
        });
        $breakers = $this->breakers(CircuitPolicy::of(100, 3, 60, 30, 1, 500, 60));
        $client = new CircuitBreakingHttpClient($inner, $breakers, 'http.payments');

        self::assertSame(200, $client->request('GET', 'https://pay.example.com/a')->getStatusCode());
        self::assertSame(200, $client->request('GET', 'https://pay.example.com/b')->getStatusCode());
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('http.payments'))->state());
        self::assertSame(1, new CacheCircuitStateStore($this->pool)->load(CircuitKey::of('http.payments'))?->slowCalls, 'a request of exactly 500 ms is not slow');
        self::assertSame(200, $client->request('GET', 'https://pay.example.com/c')->getStatusCode());
        self::assertSame(CircuitState::Open, $breakers->get(CircuitKey::of('http.payments'))->state());
    }

    /**
     * The transport's seconds are rounded to the nearest millisecond: 500.4 ms is 500, 500.6 ms is 501.
     */
    public function testTheTransportTimeIsRoundedToTheNearestMillisecond(): void
    {
        $answers = [new MockResponse('ok', ['total_time' => 0.5004]), new MockResponse('ok', ['total_time' => 0.5006])];
        $inner = new MockHttpClient(static function () use (&$answers): MockResponse {
            return array_shift($answers) ?? new MockResponse('ok');
        });
        $client = new CircuitBreakingHttpClient($inner, $this->breakers(CircuitPolicy::of(100, 10, 60, 30, 1, 500)), 'http.payments');
        $store = new CacheCircuitStateStore($this->pool);

        self::assertSame(200, $client->request('GET', 'https://pay.example.com/a')->getStatusCode());
        self::assertSame(0, $store->load(CircuitKey::of('http.payments'))?->slowCalls);
        self::assertSame(200, $client->request('GET', 'https://pay.example.com/b')->getStatusCode());
        self::assertSame(1, $store->load(CircuitKey::of('http.payments'))->slowCalls);
    }

    /**
     * A `total_time` that is not a number is no duration: the request is measured itself, and a mock answers at once.
     */
    public function testATransportTimeThatIsNotANumberIsNotRead(): void
    {
        $breakers = $this->breakers(CircuitPolicy::of(100, 1, 60, 30, 1, 1000, 1));
        $client = new CircuitBreakingHttpClient(new MockHttpClient(new MockResponse('ok', ['total_time' => '2'])), $breakers, 'http.payments');

        self::assertSame(200, $client->request('GET', 'https://pay.example.com/a')->getStatusCode());
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('http.payments'))->state());
    }

    /**
     * Without a positive `total_time`, the request is measured from when it was made: a mock answers at once.
     */
    public function testWithoutATransportTimeTheRequestIsMeasuredItself(): void
    {
        $breakers = $this->breakers(CircuitPolicy::of(100, 1, 60, 30, 1, 1000, 1));
        $client = new CircuitBreakingHttpClient(new MockHttpClient(new MockResponse('ok', ['total_time' => 0.0])), $breakers, 'http.payments');

        self::assertSame(200, $client->request('GET', 'https://pay.example.com/a')->getStatusCode());
        self::assertSame(CircuitState::Closed, $breakers->get(CircuitKey::of('http.payments'))->state());

        $slow = $this->breakers(CircuitPolicy::of(100, 1, 60, 30, 1, 1, 1));
        $client = new CircuitBreakingHttpClient(new MockHttpClient(static function (): MockResponse {
            usleep(5000);

            return new MockResponse('ok', ['total_time' => 0.0]);
        }), $slow, 'http.payments');

        self::assertSame(200, $client->request('GET', 'https://pay.example.com/b')->getStatusCode());
        self::assertSame(CircuitState::Open, $slow->get(CircuitKey::of('http.payments'))->state());
    }

    public function testASlowTransportErrorIsASlowFailure(): void
    {
        $breakers = $this->breakers(CircuitPolicy::of(100, 2, 60, 30, 1, 100, 100));
        $client = new CircuitBreakingHttpClient(new MockHttpClient(new MockResponse('', ['error' => 'Timeout.', 'total_time' => 3])), $breakers, 'http.payments');

        try {
            $client->request('GET', 'https://pay.example.com/a')->getStatusCode();
            self::fail('The transport error was lost.');
        } catch (TransportExceptionInterface) {
        }

        $loaded = new CacheCircuitStateStore($this->pool)->load(CircuitKey::of('http.payments'));
        self::assertSame([1, 1, 1], [$loaded?->calls, $loaded?->failures, $loaded?->slowCalls]);
        $breakers->get(CircuitKey::of('http.payments'))->record(CallOutcome::succeeded(101));
        self::assertSame(CircuitState::Open, $breakers->get(CircuitKey::of('http.payments'))->state());
    }

    public function testATransportErrorIsAFailure(): void
    {
        $breakers = $this->breakers(CircuitPolicy::of(50, 1, 60, 30));
        $client = new CircuitBreakingHttpClient(new MockHttpClient(new MockResponse('', ['error' => 'Connection refused.'])), $breakers, 'http.payments');

        try {
            $client->request('GET', 'https://pay.example.com/a')->getStatusCode();
            self::fail('The transport error was lost.');
        } catch (TransportExceptionInterface $transportException) {
            self::assertStringContainsString('Connection refused.', $transportException->getMessage());
        }

        self::assertSame(CircuitState::Open, $breakers->get(CircuitKey::of('http.payments'))->state());
    }

    public function testAForcedOpenCircuitSendsNothing(): void
    {
        $this->override->forcedOpen = true;
        $client = new CircuitBreakingHttpClient(new MockHttpClient(static fn (): MockResponse => self::fail('A request was sent.')), $this->breakers(CircuitPolicy::of(50, 1, 60, 30)), 'http.payments');

        $this->expectException(CircuitOpen::class);
        $client->request('GET', 'https://pay.example.com/a');
    }

    public function testTheProfilerShowsTheStateOfEachCircuit(): void
    {
        $breakers = $this->breakers(CircuitPolicy::of(50, 1, 60, 30));
        $collector = new CircuitBreakerDataCollector($breakers);
        $collector->collect(new Request(), new Response());
        self::assertSame([['http.payments' => 'closed', 'http.search' => 'closed'], 0], [$collector->circuits(), $collector->trippedCount()]);

        $breakers->get(CircuitKey::of('http.payments'))->recordFailure();
        $collector->collect(new Request(), new Response());

        self::assertSame(['http.payments' => 'open', 'http.search' => 'closed'], $collector->circuits());
        self::assertSame(1, $collector->trippedCount());
        self::assertSame(['phil_circuit_breaker', '@PhilCircuitBreaker/profiler/collector.html.twig'], [$collector->getName(), CircuitBreakerDataCollector::TEMPLATE]);

        $this->clock->advance(30);
        $collector->collect(new Request(), new Response());
        self::assertSame(['http.payments' => 'half_open', 'http.search' => 'closed'], $collector->circuits());
        self::assertSame(1, $collector->trippedCount(), 'a half-open circuit is not closed');

        $collector->reset();
        self::assertSame([[], 0], [$collector->circuits(), $collector->trippedCount()]);
    }

    private function breakers(CircuitPolicy $policy): CircuitBreakers
    {
        $store = new CacheCircuitStateStore($this->pool);

        return new CircuitBreakers([
            new CircuitBreaker(CircuitKey::of('http.payments'), $policy, $store, $this->override, $this->clock),
            new CircuitBreaker(CircuitKey::of('http.search'), $policy, $store, new SwitchableOverride(), $this->clock),
        ]);
    }
}
