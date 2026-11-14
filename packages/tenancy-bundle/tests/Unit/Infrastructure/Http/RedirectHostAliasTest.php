<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Http;

use Phil\TenancyBundle\Infrastructure\Http\ConfiguredHostTenants;
use Phil\TenancyBundle\Infrastructure\Http\RedirectHostAlias;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(RedirectHostAlias::class)]
final class RedirectHostAliasTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function redirects(): iterable
    {
        yield 'a page' => ['GET', 'https://www.rose.example/cart?item=1&size=m', 'https://rose.example/cart?item=1&size=m', 301];
        yield 'a head' => ['HEAD', 'https://www.rose.example/', 'https://rose.example/', 301];
        yield 'a form' => ['POST', 'https://www.rose.example/cart', 'https://rose.example/cart', 308];
        yield 'an option' => ['OPTIONS', 'https://www.rose.example/cart', 'https://rose.example/cart', 308];
        yield 'plain http' => ['GET', 'http://www.rose.example/', 'http://rose.example/', 301];
        yield 'another port' => ['GET', 'https://www.rose.example:8443/cart', 'https://rose.example:8443/cart', 301];
        yield 'the port of the other scheme' => ['GET', 'http://www.rose.example:443/', 'http://rose.example:443/', 301];
        yield 'a path that looks like a host' => ['GET', 'https://www.rose.example//evil.example/', 'https://rose.example//evil.example/', 301];
    }

    #[DataProvider('redirects')]
    public function testAnAliasIsSentToItsCanonicalHost(string $method, string $address, string $location, int $status): void
    {
        $event = $this->event(Request::create($address, $method));

        $this->listener()($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($location, $response->getTargetUrl());
        self::assertSame($status, $response->getStatusCode());
    }

    public function testAHostThatIsNoAliasIsLeftAlone(): void
    {
        $event = $this->event(Request::create('https://rose.example/cart'));

        $this->listener()($event);

        self::assertFalse($event->hasResponse());
    }

    public function testASubRequestIsLeftAlone(): void
    {
        $event = new RequestEvent(self::createStub(HttpKernelInterface::class), Request::create('https://www.rose.example/'), HttpKernelInterface::SUB_REQUEST);

        $this->listener()($event);

        self::assertFalse($event->hasResponse());
    }

    private function listener(): RedirectHostAlias
    {
        return new RedirectHostAlias(new ConfiguredHostTenants(['rose.example' => 'rose'], ['www.rose.example' => 'rose.example']));
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
