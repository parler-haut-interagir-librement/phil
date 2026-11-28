<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Phil\RateLimitBundle\Infrastructure\Http\RateLimitListener;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Enforcers;
use Phil\RateLimitBundle\Tests\Unit\Fixture\ScriptedLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(RateLimitListener::class)]
final class RateLimitListenerTest extends TestCase
{
    public function testTheRouteDefaultCountsAndTheResponseCarriesTheHeaders(): void
    {
        $listener = new RateLimitListener(Enforcers::of());

        foreach (['2', '1', '0'] as $remaining) {
            $request = $this->request('api');
            $listener->onKernelController($this->controllerEvent($request));

            $response = $this->respond($listener, $request);

            self::assertSame('"api";q=3;w=60', $response->headers->get('RateLimit-Policy'));
            self::assertSame('"api";r='.$remaining.';t=60', $response->headers->get('RateLimit'));
        }

        $this->expectException(TooManyRequests::class);
        $listener->onKernelController($this->controllerEvent($this->request('api')));
    }

    public function testTheRulesAreReadThenRemovedForSymfonysAttribute(): void
    {
        $api = new ScriptedLimiter(10);
        $listener = new RateLimitListener(Enforcers::of(['api' => $api]));
        $request = $this->request([['limiter' => 'api', 'amount' => 3], 'api']);

        $listener->onKernelController($this->controllerEvent($request));

        self::assertSame([['alice', 3], ['alice', 1]], $api->calls);
        self::assertSame(6, $this->decision($request)->remaining);
        self::assertFalse($request->attributes->has(RateLimitListener::ROUTE_DEFAULT));
    }

    public function testAnEarlierDecisionThatLeavesLessIsKept(): void
    {
        $listener = new RateLimitListener(Enforcers::of());
        $request = $this->request('wide');
        $tighter = Decision::accepted(10, 1, 30)->named('attribute');
        $request->attributes->set(RateLimitListener::DECISION_ATTRIBUTE, $tighter);

        $listener->onKernelController($this->controllerEvent($request));

        self::assertSame($tighter, $this->decision($request));
    }

    public function testABadRouteDefaultFails(): void
    {
        $this->expectException(InvalidRateLimit::class);
        new RateLimitListener(Enforcers::of())->onKernelController($this->controllerEvent($this->request(3)));
    }

    public function testNothingIsCountedWithoutRuleOrKeyOrOnASubRequest(): void
    {
        $api = new ScriptedLimiter(10);
        $listener = new RateLimitListener(Enforcers::of(['api' => $api]));

        $request = $this->request(null);
        $listener->onKernelController($this->controllerEvent($request));
        self::assertFalse($request->attributes->has(RateLimitListener::DECISION_ATTRIBUTE));

        $anonymous = Request::create('/');
        $anonymous->attributes->set(RateLimitListener::ROUTE_DEFAULT, 'api');

        $listener->onKernelController($this->controllerEvent($anonymous));
        self::assertFalse($anonymous->attributes->has(RateLimitListener::DECISION_ATTRIBUTE));
        self::assertSame([], $this->respond($listener, $anonymous)->headers->all('ratelimit'));

        $sub = $this->request(3);
        $listener->onKernelController($this->controllerEvent($sub, HttpKernelInterface::SUB_REQUEST));
        self::assertFalse($sub->attributes->has(RateLimitListener::DECISION_ATTRIBUTE));
        self::assertFalse($sub->attributes->has(RateLimitListener::ROUTE_DEFAULT));
        self::assertSame([], $api->calls);
    }

    public function testNoHeaderOnASubRequestNorOnARefusal(): void
    {
        $listener = new RateLimitListener(Enforcers::of());
        $request = $this->request(null);
        $request->attributes->set(RateLimitListener::DECISION_ATTRIBUTE, Decision::accepted(3, 1, 9)->named('api'));

        self::assertSame([], $this->respond($listener, $request, HttpKernelInterface::SUB_REQUEST)->headers->all('ratelimit'));
        self::assertSame([], $this->respond($listener, $request, status: 429)->headers->all('ratelimit'));
        self::assertSame('"api";r=1;t=9', $this->respond($listener, $request)->headers->get('RateLimit'));
    }

    public function testTheNames(): void
    {
        self::assertSame('_phil_rate_limit', RateLimitListener::DECISION_ATTRIBUTE);
        self::assertSame('_rate_limit', RateLimitListener::ROUTE_DEFAULT);
    }

    private function decision(Request $request): Decision
    {
        $decision = $request->attributes->get(RateLimitListener::DECISION_ATTRIBUTE);
        self::assertInstanceOf(Decision::class, $decision);

        return $decision;
    }

    private function request(mixed $rules): Request
    {
        $request = Request::create('/');
        $request->headers->set('X-Account', 'alice');

        if (null !== $rules) {
            $request->attributes->set(RateLimitListener::ROUTE_DEFAULT, $rules);
        }

        return $request;
    }

    private function controllerEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): ControllerEvent
    {
        return new ControllerEvent(self::createStub(HttpKernelInterface::class), static fn (): Response => new Response(), $request, $type);
    }

    private function respond(RateLimitListener $listener, Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, int $status = 200): Response
    {
        $event = new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, $type, new Response('', $status));
        $listener->onKernelResponse($event);

        return $event->getResponse();
    }
}
