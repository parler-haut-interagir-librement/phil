<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Application;

use Phil\RateLimitBundle\Application\Event\RateLimitExceeded;
use Phil\RateLimitBundle\Application\Event\RateLimitObserved;
use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Domain\Exception\UnknownLimiter;
use Phil\RateLimitBundle\Domain\HeaderStyle;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Enforcers;
use Phil\RateLimitBundle\Tests\Unit\Fixture\RecordingEvents;
use Phil\RateLimitBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\RateLimitBundle\Tests\Unit\Fixture\ScriptedLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(RateLimitEnforcer::class)]
#[CoversClass(Limiters::class)]
#[CoversClass(KeyResolvers::class)]
#[CoversClass(TooManyRequests::class)]
#[CoversClass(RateLimitExceeded::class)]
#[CoversClass(RateLimitObserved::class)]
final class RateLimitEnforcerTest extends TestCase
{
    public function testEachRuleConsumesUntilTheLimiterRefuses(): void
    {
        $api = new ScriptedLimiter(3);
        $events = new RecordingEvents();
        $enforcer = Enforcers::of(['api' => $api], $events);

        foreach ([2, 1, 0] as $remaining) {
            self::assertEquals(new Decision(true, 3, $remaining, 60, 0, 60, 'api'), $enforcer->enforce($this->request(), [new RateLimit('api')]));
        }

        $request = $this->request();
        $request->attributes->set('_route', 'search');

        try {
            $enforcer->enforce($request, [new RateLimit('api')]);
            self::fail('The fourth request is refused.');
        } catch (TooManyRequests $tooManyRequests) {
            $refused = new Decision(false, 3, 0, 60, 60, null, 'api');
            self::assertEquals($refused, $tooManyRequests->decision);
            self::assertSame(429, $tooManyRequests->getStatusCode());
            self::assertSame('Too many requests.', $tooManyRequests->getMessage());
            self::assertSame(0, $tooManyRequests->getCode());
            self::assertSame(['RateLimit-Policy' => '"api";q=3', 'RateLimit' => '"api";r=0;t=60', 'Retry-After' => 60], $tooManyRequests->getHeaders());
            self::assertEquals([new RateLimitExceeded('api', 'alice', $refused, 'search')], $events->events);
        }

        self::assertSame([['alice', 1], ['alice', 1], ['alice', 1], ['alice', 1]], $api->calls);
    }

    public function testTheRefusalNamesNoRouteWithoutOneAndWorksWithoutDispatcher(): void
    {
        $events = new RecordingEvents();
        $enforcer = Enforcers::of(['api' => new ScriptedLimiter(1)], $events);
        $enforcer->enforce($this->request(), [new RateLimit('api')]);

        try {
            $enforcer->enforce($this->request(), [new RateLimit('api')], 'contact');
            $enforcer->enforce($this->request(), [new RateLimit('api')], 'contact');
            self::fail('The second submission is refused.');
        } catch (TooManyRequests) {
            self::assertCount(1, $events->events);
            self::assertInstanceOf(RateLimitExceeded::class, $events->events[0]);
            self::assertNull($events->events[0]->route);
            self::assertSame('contact', $events->events[0]->form);
            self::assertSame('alice|form:contact', $events->events[0]->key);
        }

        $this->expectException(TooManyRequests::class);
        $quiet = Enforcers::of(['api' => new ScriptedLimiter(1)]);
        $quiet->enforce($this->request(), [new RateLimit('api', 2)]);
    }

    public function testALimiterInObservationReportsAndLetsTheRequestGoOn(): void
    {
        $watched = new ScriptedLimiter(1);
        $events = new RecordingEvents();
        $logger = new RecordingLogger();
        $enforcer = Enforcers::of(['watched' => $watched, 'wide' => new ScriptedLimiter(10, 30)], $events, observed: ['watched'], logger: $logger);

        $first = $enforcer->enforce($this->request(), [new RateLimit('watched'), new RateLimit('wide')], 'contact');
        self::assertSame(['watched', 0], [$first?->limiter, $first?->remaining]);
        self::assertSame([], $events->events);

        $request = $this->request();
        $request->attributes->set('_route', 'search');

        $second = $enforcer->enforce($request, [new RateLimit('watched'), new RateLimit('wide')], 'contact');

        self::assertSame(['wide', 8], [$second?->limiter, $second?->remaining], 'The refusal is left out of the headers.');
        $refused = new Decision(false, 1, 0, 60, 60, null, 'watched');
        self::assertEquals([new RateLimitObserved('watched', 'alice|form:contact', $refused, 'search', 'contact')], $events->events);
        self::assertSame([[LogLevel::WARNING, 'The rate limit "{limiter}" in observation would have refused a request.', ['limiter' => 'watched', 'route' => 'search', 'form' => 'contact', 'limit' => 1, 'retry_after' => 60]]], $logger->records);

        $quiet = Enforcers::of(['watched' => new ScriptedLimiter(1)], observed: ['watched']);
        $quiet->enforce($this->request(), [new RateLimit('watched')]);
        self::assertNull($quiet->enforce($this->request(), [new RateLimit('watched')]), 'Neither a dispatcher nor a logger is needed.');
    }

    public function testAnEnforcedLimiterBesideAnObservedOneStillRefuses(): void
    {
        $events = new RecordingEvents();
        $logger = new RecordingLogger();
        $enforcer = Enforcers::of(['watched' => new ScriptedLimiter(1), 'api' => new ScriptedLimiter(1)], $events, observed: ['watched'], logger: $logger);
        $enforcer->enforce($this->request(), [new RateLimit('watched'), new RateLimit('api')]);

        try {
            $enforcer->enforce($this->request(), [new RateLimit('watched'), new RateLimit('api')]);
            self::fail('The enforced limiter refuses.');
        } catch (TooManyRequests $tooManyRequests) {
            self::assertSame('api', $tooManyRequests->decision->limiter);
            self::assertCount(2, $events->events);
            self::assertInstanceOf(RateLimitObserved::class, $events->events[0]);
            self::assertInstanceOf(RateLimitExceeded::class, $events->events[1]);
            self::assertNull($events->events[0]->route);
            self::assertNull($events->events[0]->form);
            self::assertCount(1, $logger->records);
        }
    }

    public function testTheAmountAndKeyOfTheRuleWinOverTheLimiters(): void
    {
        $api = new ScriptedLimiter(10);
        $request = $this->request();
        $request->headers->set('X-Other', 'bob');

        Enforcers::of(['api' => $api])->enforce($request, [new RateLimit('api', 4, 'account'), new RateLimit('api')]);

        self::assertSame([['bob', 4], ['alice', 1]], $api->calls);
    }

    public function testTheTightestAcceptedDecisionIsReturned(): void
    {
        $enforcer = Enforcers::of();

        $decision = $enforcer->enforce($this->request(), [new RateLimit('wide'), new RateLimit('api'), new RateLimit('free')]);
        self::assertSame(['api', 2], [$decision?->limiter, $decision?->remaining]);

        $decision = $enforcer->enforce($this->request(), [new RateLimit('free'), new RateLimit('api'), new RateLimit('wide', 8)]);
        self::assertSame(['api', 1], [$decision?->limiter, $decision?->remaining]);

        $decision = $enforcer->enforce($this->request(), [new RateLimit('api'), new RateLimit('wide')]);
        self::assertSame(['api', 0], [$decision?->limiter, $decision?->remaining]);

        self::assertNull($enforcer->enforce($this->request(), [new RateLimit('free')])?->limit);
    }

    public function testARequestWithoutKeyIsNotCounted(): void
    {
        $api = new ScriptedLimiter(1);

        self::assertNull(Enforcers::of(['api' => $api])->enforce(Request::create('/'), [new RateLimit('api'), new RateLimit('api')]));
        self::assertSame([], $api->calls);

        Enforcers::of(['api' => $api])->enforce($this->request(), [new RateLimit('api', key: 'account'), new RateLimit('api')]);
        self::assertSame([['alice', 1]], $api->calls, 'A rule without key does not stop the next.');
        self::assertNull(Enforcers::of()->enforce($this->request(), []));
    }

    public function testALongKeyIsHashed(): void
    {
        $api = new ScriptedLimiter(10);
        $enforcer = Enforcers::of(['api' => $api]);
        $long = str_repeat('k', 200);

        $enforcer->enforce($this->request($long), [new RateLimit('api')]);
        $enforcer->enforce($this->request($long.'k'), [new RateLimit('api')]);

        self::assertSame([[$long, 1], ['sha256:'.hash('sha256', $long.'k'), 1]], $api->calls);
        self::assertSame(200, RateLimitEnforcer::MAX_KEY_LENGTH);
    }

    public function testTheHeadersOfADecision(): void
    {
        self::assertSame(['RateLimit-Limit' => '3', 'RateLimit-Remaining' => '1', 'RateLimit-Reset' => '9'], Enforcers::of(style: HeaderStyle::Legacy)->headersOf(Decision::accepted(3, 1, 9)));
    }

    public function testAnUnknownLimiterOrStrategyIsALogicError(): void
    {
        $enforcer = Enforcers::of();

        try {
            $enforcer->enforce(Request::create('/'), [new RateLimit('nope')]);
            self::fail('An unknown limiter is refused.');
        } catch (UnknownLimiter $unknownLimiter) {
            self::assertSame('No rate limiter is named "nope"; the limiters declared are "api", "wide", "free".', $unknownLimiter->getMessage());
        }

        $this->expectExceptionObject(UnknownLimiter::key('nope', ['header', 'account']));

        $enforcer->enforce($this->request(), [new RateLimit('api', key: 'nope')]);
    }

    private function request(string $account = 'alice'): Request
    {
        $request = Request::create('/');
        $request->headers->set('X-Account', $account);

        return $request;
    }
}
