<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
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
    public function testTheAttributesCountAndTheResponseCarriesTheHeaders(): void
    {
        $listener = new RateLimitListener(Enforcers::of());

        foreach (['2', '1', '0'] as $remaining) {
            $request = $this->request();
            $listener->onKernelController($this->controllerEvent($request, [new RateLimit('api')]));

            $response = $this->respond($listener, $request);

            self::assertSame('"api";q=3;w=60', $response->headers->get('RateLimit-Policy'));
            self::assertSame('"api";r='.$remaining.';t=60', $response->headers->get('RateLimit'));
        }

        $this->expectException(TooManyRequests::class);
        $listener->onKernelController($this->controllerEvent($this->request(), [new RateLimit('api')]));
    }

    public function testTheRouteDefaultCountsAfterTheAttributes(): void
    {
        $api = new ScriptedLimiter(10);
        $listener = new RateLimitListener(Enforcers::of(['api' => $api]));
        $request = $this->request();
        $request->attributes->set(RateLimitListener::ROUTE_DEFAULT, ['limiter' => 'api', 'amount' => 3]);

        $listener->onKernelController($this->controllerEvent($request, [new RateLimit('api', 2)]));

        self::assertSame([['alice', 2], ['alice', 3]], $api->calls);
        self::assertSame(5, $this->decision($request)->remaining);

        $route = $this->request();
        $route->attributes->set(RateLimitListener::ROUTE_DEFAULT, 'api');

        $listener->onKernelController($this->controllerEvent($route, []));

        self::assertSame(4, $this->decision($route)->remaining);
    }

    public function testABadRouteDefaultFails(): void
    {
        $request = $this->request();
        $request->attributes->set(RateLimitListener::ROUTE_DEFAULT, 3);

        $this->expectException(InvalidRateLimit::class);
        new RateLimitListener(Enforcers::of())->onKernelController($this->controllerEvent($request, []));
    }

    public function testNothingIsCountedWithoutRuleOrKeyOrOnASubRequest(): void
    {
        $api = new ScriptedLimiter(10);
        $listener = new RateLimitListener(Enforcers::of(['api' => $api]));

        $request = $this->request();
        $listener->onKernelController($this->controllerEvent($request, []));
        self::assertFalse($request->attributes->has(RateLimitListener::DECISION_ATTRIBUTE));

        $anonymous = Request::create('/');
        $listener->onKernelController($this->controllerEvent($anonymous, [new RateLimit('api')]));
        self::assertFalse($anonymous->attributes->has(RateLimitListener::DECISION_ATTRIBUTE));
        self::assertSame([], $this->respond($listener, $anonymous)->headers->all('ratelimit'));

        $sub = $this->request();
        $listener->onKernelController($this->controllerEvent($sub, [new RateLimit('api')], HttpKernelInterface::SUB_REQUEST));
        self::assertFalse($sub->attributes->has(RateLimitListener::DECISION_ATTRIBUTE));
        self::assertSame([], $api->calls);
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

    private function request(): Request
    {
        $request = Request::create('/');
        $request->headers->set('X-Account', 'alice');

        return $request;
    }

    /**
     * @param list<RateLimit> $attributes
     */
    private function controllerEvent(Request $request, array $attributes, int $type = HttpKernelInterface::MAIN_REQUEST): ControllerEvent
    {
        $event = new ControllerEvent(self::createStub(HttpKernelInterface::class), static fn (): Response => new Response(), $request, $type);
        $event->setController($event->getController(), $attributes);

        return $event;
    }

    private function respond(RateLimitListener $listener, Request $request): Response
    {
        $event = new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
        $listener->onKernelResponse($event);

        return $event->getResponse();
    }
}
