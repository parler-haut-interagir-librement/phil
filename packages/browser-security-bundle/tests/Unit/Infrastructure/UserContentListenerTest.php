<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use Phil\BrowserSecurityBundle\Application\Attribute\UserContent;
use Phil\BrowserSecurityBundle\Application\UserContentPolicy;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Infrastructure\Http\UserContentListener;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\NullKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(UserContentListener::class)]
#[CoversClass(UserContent::class)]
#[UsesClass(UserContentPolicy::class)]
final class UserContentListenerTest extends TestCase
{
    #[UserContent]
    public function downloadAction(): Response
    {
        return new Response();
    }

    public function plainAction(): Response
    {
        return new Response();
    }

    public function testTheAttributeMarksTheMainRequest(): void
    {
        $listener = new UserContentListener(new UserContentPolicy());
        $main = Request::create('/file');
        $sub = Request::create('/file');
        $plain = Request::create('/page');

        $listener->onKernelController(new ControllerEvent(new NullKernel(), $this->downloadAction(...), $main, HttpKernelInterface::MAIN_REQUEST));
        $listener->onKernelController(new ControllerEvent(new NullKernel(), $this->downloadAction(...), $sub, HttpKernelInterface::SUB_REQUEST));
        $listener->onKernelController(new ControllerEvent(new NullKernel(), $this->plainAction(...), $plain, HttpKernelInterface::MAIN_REQUEST));

        self::assertTrue($main->attributes->get(UserContentPolicy::ROUTE_DEFAULT));
        self::assertFalse($sub->attributes->has(UserContentPolicy::ROUTE_DEFAULT));
        self::assertFalse($plain->attributes->has(UserContentPolicy::ROUTE_DEFAULT));
    }

    public function testAMarkedResponseGetsThePolicy(): void
    {
        $request = Request::create('/file');
        $request->attributes->set(UserContentPolicy::ROUTE_DEFAULT, true);

        $response = $this->respond($request, HttpKernelInterface::MAIN_REQUEST);

        self::assertSame('attachment', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testAnUnmarkedOrSubResponseIsLeftAlone(): void
    {
        $off = Request::create('/file');
        $off->attributes->set(UserContentPolicy::ROUTE_DEFAULT, false);

        $sub = Request::create('/file');
        $sub->attributes->set(UserContentPolicy::ROUTE_DEFAULT, true);

        self::assertFalse($this->respond(Request::create('/page'), HttpKernelInterface::MAIN_REQUEST)->headers->has('Content-Disposition'));
        self::assertFalse($this->respond($off, HttpKernelInterface::MAIN_REQUEST)->headers->has('Content-Disposition'));
        self::assertFalse($this->respond($sub, HttpKernelInterface::SUB_REQUEST)->headers->has('Content-Disposition'));
    }

    public function testTheRouteDefaultIsABoolean(): void
    {
        $request = Request::create('/file');
        $request->attributes->set(UserContentPolicy::ROUTE_DEFAULT, 'yes');

        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('The route default "_user_content" is true or false.');

        $this->respond($request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function respond(Request $request, int $type): Response
    {
        $response = new Response('data', Response::HTTP_OK, ['Content-Type' => 'application/zip']);
        new UserContentListener(new UserContentPolicy())->onKernelResponse(new ResponseEvent(new NullKernel(), $request, $type, $response));

        return $response;
    }
}
