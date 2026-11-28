<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use LogicException;
use Override;
use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\AttributeLimiter;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\AttributeLimiterFactory;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\AttributeLimits;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Enforcers;
use Phil\RateLimitBundle\Tests\Unit\Fixture\HeaderKey;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Locator;
use Phil\RateLimitBundle\Tests\Unit\Fixture\RecordingEvents;
use Phil\RateLimitBundle\Tests\Unit\Fixture\ScriptedLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;

#[CoversClass(AttributeLimits::class)]
#[CoversClass(AttributeLimiterFactory::class)]
#[CoversClass(AttributeLimiter::class)]
final class AttributeLimitsTest extends TestCase
{
    private ScriptedLimiter $api;

    private RequestStack $requests;

    private Request $request;

    #[Override]
    protected function setUp(): void
    {
        $this->api = new ScriptedLimiter(3);
        $this->requests = new RequestStack();
        $this->request = Request::create('/articles/42', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $this->request->headers->set('X-Account', 'alice');
        $this->request->headers->set('X-Other', 'bob');

        $this->requests->push($this->request);
    }

    public function testNoKeyOrSymfonysDefaultTakesTheLimitersStrategy(): void
    {
        $factory = new AttributeLimiterFactory($this->limits(), 'api');

        self::assertSame(2, $factory->create()->consume()->getRemainingTokens());
        self::assertSame(1, $factory->create('203.0.113.7~/articles/42')->consume()->getRemainingTokens());
        self::assertSame([['alice', 1], ['alice', 1]], $this->api->calls);
    }

    public function testAKeyThatNamesAStrategyTakesIt(): void
    {
        new AttributeLimiterFactory($this->limits(), 'api')->create('account')->consume(2);

        self::assertSame([['bob', 2]], $this->api->calls);
    }

    public function testAnyOtherKeyIsKeptAsItsHash(): void
    {
        new AttributeLimiterFactory($this->limits(), 'api')->create('42')->consume();

        self::assertSame([['key:'.hash_hmac('sha256', '42', hash_hkdf('sha256', 'secret', 32, 'phil_rate_limit.attribute_key')), 1]], $this->api->calls);
        self::assertSame('phil_rate_limit.attribute_key', AttributeLimits::USAGE);
    }

    public function testTheAcceptedDecisionIsRememberedForTheHeaders(): void
    {
        $limit = new AttributeLimiterFactory($this->limits(), 'api')->create()->consume();

        self::assertTrue($limit->isAccepted());
        self::assertSame(3, $limit->getLimit());
        $decision = $this->request->attributes->get(RateLimitEnforcer::DECISION_ATTRIBUTE);
        self::assertInstanceOf(Decision::class, $decision);
        self::assertSame(['api', 2], [$decision->limiter, $decision->remaining]);
    }

    public function testARequestWithoutKeyIsNotCounted(): void
    {
        $this->request->headers->remove('X-Account');

        $limit = new AttributeLimiterFactory($this->limits(), 'api')->create()->consume(3);

        self::assertTrue($limit->isAccepted());
        self::assertSame(\PHP_INT_MAX, $limit->getLimit());
        self::assertSame([], $this->api->calls);
        self::assertFalse($this->request->attributes->has(RateLimitEnforcer::DECISION_ATTRIBUTE));
    }

    public function testARefusalIsThrownAndDispatched(): void
    {
        $events = new RecordingEvents();
        $limiter = new AttributeLimiterFactory($this->limits($events), 'api')->create();
        $limiter->consume(3);

        try {
            $limiter->consume();
            self::fail('The fourth token is refused.');
        } catch (TooManyRequests $tooManyRequests) {
            self::assertSame('api', $tooManyRequests->decision->limiter);
            self::assertCount(2, $events->events);
        }
    }

    public function testARefusalInObservationLetsTheRequestGoOn(): void
    {
        $limits = $this->limits(observed: ['api']);
        $factory = new AttributeLimiterFactory($limits, 'api');
        $factory->create()->consume(3);
        $kept = $this->request->attributes->get(RateLimitEnforcer::DECISION_ATTRIBUTE);

        $limit = $factory->create()->consume();

        self::assertTrue($limit->isAccepted());
        self::assertSame($kept, $this->request->attributes->get(RateLimitEnforcer::DECISION_ATTRIBUTE));
    }

    public function testNoRequestNoCount(): void
    {
        $this->requests->pop();

        $this->expectExceptionObject(new LogicException('The rate limiter "api" of phil/rate-limit-bundle counts the requests of #[RateLimit], and no request is being handled.'));

        new AttributeLimiterFactory($this->limits(), 'api')->create()->consume();
    }

    public function testTheLimiterNeitherReservesNorResets(): void
    {
        $limiter = new AttributeLimiterFactory($this->limits(), 'api')->create('42');

        try {
            $limiter->reset();
            self::fail('A reset is refused.');
        } catch (LogicException $logicException) {
            self::assertSame('The rate limiter "api" of #[RateLimit] is reset through its own storage.', $logicException->getMessage());
        }

        $this->expectException(ReserveNotSupportedException::class);
        $limiter->reserve();
    }

    /**
     * @return iterable<string, array{Request, string}>
     */
    public static function defaultKeys(): iterable
    {
        yield 'an IPv4 address' => [Request::create('/search', server: ['REMOTE_ADDR' => '203.0.113.7']), '203.0.113.7~/search'];
        yield 'an IPv6 address by its /64' => [Request::create('/search', server: ['REMOTE_ADDR' => '2001:db8:1:2:3:4:5:6']), '2001:db8:1:2::~/search'];
        yield 'the path decoded and encoded again' => [Request::create('/a%20b/%7Ec', server: ['REMOTE_ADDR' => '203.0.113.7']), '203.0.113.7~/a%20b/~c'];
        $anonymous = Request::create('/search');
        $anonymous->server->remove('REMOTE_ADDR');

        yield 'no address' => [$anonymous, 'unknown~/search'];
    }

    #[DataProvider('defaultKeys')]
    public function testSymfonysDefaultKey(Request $request, string $key): void
    {
        self::assertSame($key, AttributeLimits::defaultKeyOf($request));
    }

    /**
     * @param list<string> $observed
     */
    private function limits(?RecordingEvents $events = null, array $observed = []): AttributeLimits
    {
        $keys = ['header' => new HeaderKey(), 'account' => new HeaderKey('X-Other')];

        return new AttributeLimits(Enforcers::of(['api' => $this->api], $events, observed: $observed), new KeyResolvers(new Locator($keys), array_keys($keys)), $this->requests, 'secret');
    }
}
