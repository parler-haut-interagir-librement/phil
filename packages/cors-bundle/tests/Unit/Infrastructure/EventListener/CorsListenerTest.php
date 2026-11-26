<?php

declare(strict_types=1);

namespace Phil\CorsBundle\Tests\Unit\Infrastructure\EventListener;

use Phil\CorsBundle\Application\CorsHeaders;
use Phil\CorsBundle\Domain\CorsPolicy;
use Phil\CorsBundle\Domain\PathRule;
use Phil\CorsBundle\Infrastructure\EventListener\CorsListener;
use Phil\CorsBundle\Infrastructure\PathPolicyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(CorsListener::class)]
final class CorsListenerTest extends TestCase
{
    public function testAPreflightOfAConfiguredPathIsAnswered(): void
    {
        $event = $this->requestEvent($this->preflight('/api/books'));
        $this->listener()->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('https://a.test', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAPreflightOfAnotherPathIsLeftAlone(): void
    {
        $event = $this->requestEvent($this->preflight('/books'));
        $this->listener()->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testARequestThatIsNoPreflightIsLeftAlone(): void
    {
        $event = $this->requestEvent(Request::create('/api/books', 'OPTIONS', server: ['HTTP_ORIGIN' => 'https://a.test']));
        $this->listener()->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAPreflightInASubRequestIsLeftAlone(): void
    {
        $event = $this->requestEvent($this->preflight('/api/books'), HttpKernelInterface::SUB_REQUEST);
        $this->listener()->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAResponseOfAConfiguredPathGetsTheHeaders(): void
    {
        $event = $this->responseEvent(Request::create('/api/books', server: ['HTTP_ORIGIN' => 'https://a.test']));
        $this->listener()->onKernelResponse($event);

        self::assertSame('https://a.test', $event->getResponse()->headers->get('Access-Control-Allow-Origin'));
        self::assertSame(['Origin'], $event->getResponse()->getVary());
    }

    public function testAResponseOfAnotherPathIsLeftAlone(): void
    {
        $event = $this->responseEvent(Request::create('/books', server: ['HTTP_ORIGIN' => 'https://a.test']));
        $this->listener()->onKernelResponse($event);

        self::assertFalse($event->getResponse()->headers->has('Access-Control-Allow-Origin'));
        self::assertSame([], $event->getResponse()->getVary());
    }

    public function testAResponseOfASubRequestIsLeftAlone(): void
    {
        $event = $this->responseEvent(Request::create('/api/books', server: ['HTTP_ORIGIN' => 'https://a.test']), HttpKernelInterface::SUB_REQUEST);
        $this->listener()->onKernelResponse($event);

        self::assertSame([], $event->getResponse()->getVary());
    }

    public function testTheAnswerToAPreflightIsNotChangedAgain(): void
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->setVary(['Origin', 'Access-Control-Request-Method']);
        $response->headers->set('Access-Control-Allow-Methods', 'GET');

        $event = new ResponseEvent($this->kernel(), $this->preflight('/api/books'), HttpKernelInterface::MAIN_REQUEST, $response);
        $this->listener()->onKernelResponse($event);

        self::assertSame(['Origin', 'Access-Control-Request-Method'], $response->getVary());
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    private function listener(): CorsListener
    {
        return new CorsListener(new PathPolicyResolver([new PathRule('^/api/', new CorsPolicy(['https://a.test']))]), new CorsHeaders());
    }

    private function preflight(string $path): Request
    {
        return Request::create($path, 'OPTIONS', server: ['HTTP_ORIGIN' => 'https://a.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);
    }

    private function requestEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->kernel(), $request, $type);
    }

    private function responseEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->kernel(), $request, $type, new Response());
    }

    private function kernel(): HttpKernelInterface
    {
        return self::createStub(HttpKernelInterface::class);
    }
}
