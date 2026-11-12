<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use Phil\BrowserSecurityBundle\Application\CookieSigner;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\ExternalRedirectAction;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ExternalRedirectListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\HttpsRedirectListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\SignedCookiesListener;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\NullKernel;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(HttpsRedirectListener::class)]
#[CoversClass(SignedCookiesListener::class)]
#[CoversClass(ExternalRedirectListener::class)]
final class RedirectsAndCookiesTest extends TestCase
{
    public function testAPlainRequestGoesToHttps(): void
    {
        $response = $this->https(new HttpsRedirectListener(), Request::create('http://example.com/a?b=1'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(308, $response->getStatusCode());
        self::assertSame('https://example.com/a?b=1', $response->getTargetUrl());
    }

    public function testTheStatusAndThePort(): void
    {
        $response = $this->https(new HttpsRedirectListener(status: 301, port: 8443), Request::create('http://example.com/a'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('https://example.com:8443/a', $response->getTargetUrl());
    }

    public function testAnHttpsRequestStays(): void
    {
        self::assertNull($this->https(new HttpsRedirectListener(), Request::create('https://example.com/a')));
    }

    public function testASubRequestStays(): void
    {
        self::assertNull($this->https(new HttpsRedirectListener(), Request::create('http://example.com/a'), HttpKernelInterface::SUB_REQUEST));
    }

    public function testAnExcludedPathStays(): void
    {
        $listener = new HttpsRedirectListener(excludedPaths: ['/health']);

        self::assertNull($this->https($listener, Request::create('http://example.com/health/live')));
        self::assertNotNull($this->https($listener, Request::create('http://example.com/x/health')));
    }

    public function testTurnedOff(): void
    {
        self::assertNull($this->https(new HttpsRedirectListener(false), Request::create('http://example.com/a')));
    }

    public function testARouteTurnsItOnAndOff(): void
    {
        $on = Request::create('http://example.com/a');
        RequestState::of($on)->addProtectionOverride(new ProtectionOverride(replace: ['https-redirect' => true]));
        $off = Request::create('http://example.com/a');
        RequestState::of($off)->addProtectionOverride(new ProtectionOverride(remove: ['https-redirect']));

        self::assertNotNull($this->https(new HttpsRedirectListener(false), $on));
        self::assertNull($this->https(new HttpsRedirectListener(), $off));
    }

    public function testASignedCookieIsReadPlain(): void
    {
        $signer = new CookieSigner('secret');
        $request = Request::create('/', cookies: ['theme' => $signer->sign('theme', 'dark'), 'other' => 'kept']);

        $this->cookieRequest(new SignedCookiesListener($signer, ['theme', 'absent']), $request);

        self::assertSame('dark', $request->cookies->get('theme'));
        self::assertSame('kept', $request->cookies->get('other'));
        self::assertFalse($request->cookies->has('absent'));
    }

    public function testACookieAfterAnAbsentOneIsRead(): void
    {
        $signer = new CookieSigner('secret');
        $request = Request::create('/', cookies: ['theme' => $signer->sign('theme', 'dark')]);

        $this->cookieRequest(new SignedCookiesListener($signer, ['absent', 'theme']), $request);

        self::assertSame('dark', $request->cookies->get('theme'));
    }

    public function testACookieWithoutItsSignatureIsTakenAway(): void
    {
        $request = Request::create('/', cookies: ['theme' => 'dark']);

        $this->cookieRequest(new SignedCookiesListener(new CookieSigner('secret'), ['theme']), $request);

        self::assertFalse($request->cookies->has('theme'));
    }

    public function testACookieOfSeveralValuesIsTakenAway(): void
    {
        $request = Request::create('/', cookies: ['theme' => ['a' => 'b']]);

        $this->cookieRequest(new SignedCookiesListener(new CookieSigner('secret'), ['theme']), $request);

        self::assertFalse($request->cookies->has('theme'));
    }

    public function testASubRequestIsLeftAlone(): void
    {
        $request = Request::create('/', cookies: ['theme' => 'dark']);

        $this->cookieRequest(new SignedCookiesListener(new CookieSigner('secret'), ['theme']), $request, HttpKernelInterface::SUB_REQUEST);

        self::assertSame('dark', $request->cookies->get('theme'));
    }

    public function testANamedCookieIsSignedOnTheWayOut(): void
    {
        $signer = new CookieSigner('secret');
        $response = new Response();
        $response->headers->setCookie(Cookie::create('theme', 'dark'));
        $response->headers->setCookie(Cookie::create('other', 'kept'));

        $this->cookieResponse(new SignedCookiesListener($signer, ['theme']), Request::create('/'), $response);

        $cookies = [];

        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        self::assertSame(['theme' => $signer->sign('theme', 'dark'), 'other' => 'kept'], $cookies);
    }

    public function testADeletedCookieHasNothingToSign(): void
    {
        $response = new Response();
        $response->headers->clearCookie('theme');

        $this->cookieResponse(new SignedCookiesListener(new CookieSigner('secret'), ['theme']), Request::create('/'), $response);

        self::assertNull($response->headers->getCookies()[0]->getValue());
    }

    public function testACookieOfASubRequestStaysPlain(): void
    {
        $response = new Response();
        $response->headers->setCookie(Cookie::create('theme', 'dark'));

        $this->cookieResponse(new SignedCookiesListener(new CookieSigner('secret'), ['theme']), Request::create('/'), $response, HttpKernelInterface::SUB_REQUEST);

        self::assertSame('dark', $response->headers->getCookies()[0]->getValue());
    }

    public function testARouteChangesTheCookiesSigned(): void
    {
        $signer = new CookieSigner('secret');
        $added = Request::create('/', cookies: ['beta' => $signer->sign('beta', 'on'), 'theme' => 'plain']);
        RequestState::of($added)->addProtectionOverride(new ProtectionOverride(replace: ['signed-cookies' => ['beta']]));
        $removed = Request::create('/', cookies: ['theme' => 'plain']);
        RequestState::of($removed)->addProtectionOverride(new ProtectionOverride(remove: ['signed-cookies']));
        $listener = new SignedCookiesListener($signer, ['theme']);

        $this->cookieRequest($listener, $added);
        $this->cookieRequest($listener, $removed);

        self::assertSame('on', $added->cookies->get('beta'));
        self::assertSame('plain', $added->cookies->get('theme'));
        self::assertSame('plain', $removed->cookies->get('theme'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function external(): iterable
    {
        yield 'another host' => ['https://evil.example.net/'];
        yield 'no scheme' => ['//evil.example.net/'];
        yield 'a backslash' => ['/\evil.example.net/'];
        yield 'a tab' => ["/\t/evil.example.net/"];
        yield 'a script' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/html,x'];
        yield 'the same host, another scheme' => ['ftp://www.example.com/file'];
        yield 'unreadable' => ['https://exa mple.com:bad/'];
    }

    #[DataProvider('external')]
    public function testAnExternalRedirectIsRefused(string $location): void
    {
        $event = $this->redirected(new ExternalRedirectListener(allowed: ['allowed.example.com']), $location);

        self::assertSame(403, $event->getResponse()->getStatusCode());
        self::assertSame('', $event->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function internal(): iterable
    {
        yield 'a path' => ['/next'];
        yield 'relative' => ['next'];
        yield 'the same host' => ['https://www.example.com/next'];
        yield 'the same host in http' => ['http://www.example.com/next'];
        yield 'the host in capitals' => ['https://WWW.EXAMPLE.COM/next'];
        yield 'an allowed host' => ['https://allowed.example.com/'];
        yield 'under an allowed domain' => ['https://a.b.example.org/'];
        yield 'the domain itself written with a dot' => ['https://.example.org/'];
    }

    #[DataProvider('internal')]
    public function testAnAllowedRedirectPasses(string $location): void
    {
        $event = $this->redirected(new ExternalRedirectListener(allowed: ['allowed.example.com', '.example.org']), $location);

        self::assertSame(302, $event->getResponse()->getStatusCode());
    }

    public function testADomainDoesNotMatchTheEndOfAName(): void
    {
        $event = $this->redirected(new ExternalRedirectListener(allowed: ['example.org']), 'https://notexample.org/');

        self::assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testADomainWithoutDotIsOneHost(): void
    {
        $event = $this->redirected(new ExternalRedirectListener(allowed: ['example.org']), 'https://a.example.org/');

        self::assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testTheFallback(): void
    {
        $response = $this->redirected(new ExternalRedirectListener(ExternalRedirectAction::Fallback, fallback: '/home'), 'https://evil.example.net/')->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/home', $response->getTargetUrl());
    }

    public function testOnlyLogged(): void
    {
        $logger = $this->logger();
        $event = $this->redirected(new ExternalRedirectListener(ExternalRedirectAction::Log, logger: $logger), 'https://evil.example.net/?token=secret');

        self::assertSame('https://evil.example.net/?token=secret', $event->getResponse()->headers->get('Location'));
        self::assertSame([['warning', 'A redirect leaves the site for a host not allowed.', ['host' => 'evil.example.net', 'action' => 'log']]], $logger->records);
    }

    public function testATargetThatIsNoAddressIsLoggedWithoutHost(): void
    {
        $logger = $this->logger();
        $this->redirected(new ExternalRedirectListener(ExternalRedirectAction::Log, logger: $logger), 'https://a b/');

        self::assertSame([['warning', 'A redirect leaves the site for a host not allowed.', ['host' => null, 'action' => 'log']]], $logger->records);
    }

    public function testAPageAllowsOrTurnsOffTheCheck(): void
    {
        $allowed = Request::create('https://www.example.com/page');
        RequestState::of($allowed)->addProtectionOverride(new ProtectionOverride(add: ['external-redirects' => ['pay.example.net']]));
        $off = Request::create('https://www.example.com/page');
        RequestState::of($off)->addProtectionOverride(new ProtectionOverride(remove: ['external-redirects']));

        self::assertSame(302, $this->redirected(new ExternalRedirectListener(), 'https://pay.example.net/', $allowed)->getResponse()->getStatusCode());
        self::assertSame(302, $this->redirected(new ExternalRedirectListener(), 'https://evil.example.net/', $off)->getResponse()->getStatusCode());
    }

    public function testWhatIsNotARedirectStays(): void
    {
        $listener = new ExternalRedirectListener();
        $created = new Response('', Response::HTTP_CREATED, ['Location' => 'https://evil.example.net/']);
        $redirectWithoutLocation = new Response('', Response::HTTP_FOUND);
        $request = Request::create('https://www.example.com/page');

        foreach ([$created, $redirectWithoutLocation] as $response) {
            $event = new ResponseEvent(new NullKernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response);
            $listener->onKernelResponse($event);
            self::assertSame($response, $event->getResponse());
        }
    }

    public function testASubRequestAndAnExcludedPathStay(): void
    {
        $listener = new ExternalRedirectListener(excludedPaths: ['/_profiler']);
        $response = new RedirectResponse('https://evil.example.net/');
        $sub = new ResponseEvent(new NullKernel(), Request::create('https://www.example.com/page'), HttpKernelInterface::SUB_REQUEST, $response);
        $listener->onKernelResponse($sub);
        self::assertSame($response, $sub->getResponse());

        self::assertSame(302, $this->redirected($listener, 'https://evil.example.net/', Request::create('https://www.example.com/_profiler/x'))->getResponse()->getStatusCode());
        self::assertSame(403, $this->redirected($listener, 'https://evil.example.net/', Request::create('https://www.example.com/x/_profiler'))->getResponse()->getStatusCode());
    }

    private function https(HttpsRedirectListener $listener, Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): ?Response
    {
        $event = new RequestEvent(new NullKernel(), $request, $type);
        $listener->onKernelRequest($event);

        return $event->getResponse();
    }

    private function cookieRequest(SignedCookiesListener $listener, Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): void
    {
        $listener->onKernelRequest(new RequestEvent(new NullKernel(), $request, $type));
    }

    private function cookieResponse(SignedCookiesListener $listener, Request $request, Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): void
    {
        $listener->onKernelResponse(new ResponseEvent(new NullKernel(), $request, $type, $response));
    }

    private function redirected(ExternalRedirectListener $listener, string $location, ?Request $request = null): ResponseEvent
    {
        $event = new ResponseEvent(new NullKernel(), $request ?? Request::create('https://www.example.com/page'), HttpKernelInterface::MAIN_REQUEST, new Response('', Response::HTTP_FOUND, ['Location' => $location]));
        $listener->onKernelResponse($event);

        return $event;
    }

    private function logger(): RecordingLogger
    {
        return new RecordingLogger();
    }
}
