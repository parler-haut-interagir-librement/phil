<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use Phil\BrowserSecurityBundle\Application\Attribute\BrowserSecurity;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Application\SecurityHeaders;
use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ReportEndpoint;
use Phil\BrowserSecurityBundle\Infrastructure\Http\SecurityHeadersListener;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\NullKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(SecurityHeadersListener::class)]
final class SecurityHeadersListenerTest extends TestCase
{
    public function testTheRouteDefaultIsReadAsSoonAsTheRouteIsKnown(): void
    {
        $request = Request::create('/page');
        $request->attributes->set('_browser_security', ['remove' => ['Referrer-Policy']]);

        $this->listener()->onKernelRequest(new RequestEvent(new NullKernel(), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertNull(RequestState::of($request)->protection(Protection::ReferrerPolicy, 'origin'));
    }

    public function testASubRequestAddsNothing(): void
    {
        $request = Request::create('/page');
        $request->attributes->set('_browser_security', ['remove' => ['Referrer-Policy']]);

        $this->listener()->onKernelRequest(new RequestEvent(new NullKernel(), $request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame([], RequestState::of($request)->protectionOverrides);
    }

    public function testARequestWithoutDefaultAddsNothing(): void
    {
        $request = Request::create('/page');

        $this->listener()->onKernelRequest(new RequestEvent(new NullKernel(), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame([], RequestState::of($request)->protectionOverrides);
    }

    #[BrowserSecurity(remove: ['Referrer-Policy'])]
    #[BrowserSecurity(replace: ['X-Frame-Options' => 'DENY'])]
    public function strictAction(): Response
    {
        return new Response();
    }

    #[BrowserSecurity(remove: ['https-redirect'])]
    public function tooLateAction(): Response
    {
        return new Response();
    }

    public function testTheAttributesOfAController(): void
    {
        $request = Request::create('/page');

        $this->listener()->onKernelController(new ControllerEvent(new NullKernel(), $this->strictAction(...), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertEquals([new ProtectionOverride(remove: ['Referrer-Policy']), new ProtectionOverride(replace: ['X-Frame-Options' => 'DENY'])], RequestState::of($request)->protectionOverrides);
    }

    public function testTheAttributesOfASubRequestAreNotRead(): void
    {
        $request = Request::create('/page');

        $this->listener()->onKernelController(new ControllerEvent(new NullKernel(), $this->strictAction(...), $request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame([], RequestState::of($request)->protectionOverrides);
    }

    public function testAnAttributeCannotChangeWhatActedBefore(): void
    {
        $this->expectException(InvalidPolicy::class);

        $this->listener()->onKernelController(new ControllerEvent(new NullKernel(), $this->tooLateAction(...), Request::create('/page'), HttpKernelInterface::MAIN_REQUEST));
    }

    public function testTheHeadersAreWritten(): void
    {
        $response = $this->respond($this->listener(['Referrer-Policy' => 'origin', 'Strict-Transport-Security' => 'max-age=60']), Request::create('https://example.com/page'));

        self::assertSame('origin', $response->headers->get('Referrer-Policy'));
        self::assertSame('max-age=60', $response->headers->get('Strict-Transport-Security'));
    }

    public function testAPlainRequestGetsNoStrictTransportSecurity(): void
    {
        self::assertFalse($this->respond($this->listener(['Strict-Transport-Security' => 'max-age=60']), Request::create('http://example.com/page'))->headers->has('Strict-Transport-Security'));
    }

    public function testAResponseKeepsItsOwnHeader(): void
    {
        $response = new Response('', Response::HTTP_OK, ['Referrer-Policy' => 'same-origin']);
        $this->respond($this->listener(['Referrer-Policy' => 'origin']), Request::create('/page'), $response);

        self::assertSame('same-origin', $response->headers->get('Referrer-Policy'));
    }

    public function testAnOverrideWinsOverTheHeaderOfTheResponse(): void
    {
        $response = new Response('', Response::HTTP_OK, ['Referrer-Policy' => 'same-origin', 'X-Frame-Options' => 'DENY']);
        $request = Request::create('/page');
        RequestState::of($request)->addProtectionOverride(new ProtectionOverride(replace: ['Referrer-Policy' => 'no-referrer'], remove: ['X-Frame-Options']));
        $this->respond($this->listener(), $request, $response);

        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertFalse($response->headers->has('X-Frame-Options'));
    }

    public function testASubRequestAndAnExcludedPathGetNothing(): void
    {
        $listener = $this->listener(['Referrer-Policy' => 'origin'], excludedPaths: ['/_profiler']);

        self::assertFalse($this->respond($listener, Request::create('/page'), type: HttpKernelInterface::SUB_REQUEST)->headers->has('Referrer-Policy'));
        self::assertFalse($this->respond($listener, Request::create('/_profiler/abc'))->headers->has('Referrer-Policy'));
        self::assertTrue($this->respond($listener, Request::create('/x/_profiler'))->headers->has('Referrer-Policy'));
    }

    public function testTheEndpointIsAnnouncedWhenAHeaderNamesIt(): void
    {
        $response = $this->respond($this->listener(['Integrity-Policy-Report-Only' => ['script']], 'reports', endpoint: new ReportEndpoint(uri: 'https://r.example.com/r')), Request::create('/page'));

        self::assertSame('reports="https://r.example.com/r"', $response->headers->get('Reporting-Endpoints'));
    }

    public function testTheEndpointOfTheResponseStays(): void
    {
        $response = new Response('', Response::HTTP_OK, ['Reporting-Endpoints' => 'mine="https://m.example.com"']);
        $this->respond($this->listener(['Integrity-Policy-Report-Only' => ['script']], 'reports', endpoint: new ReportEndpoint(uri: 'https://r.example.com/r')), Request::create('/page'), $response);

        self::assertSame('mine="https://m.example.com"', $response->headers->get('Reporting-Endpoints'));
    }

    public function testNoEndpointWithoutAnAddressOrAHeaderThatNamesIt(): void
    {
        self::assertFalse($this->respond($this->listener(['Integrity-Policy-Report-Only' => ['script']], 'reports', endpoint: new ReportEndpoint()), Request::create('/page'))->headers->has('Reporting-Endpoints'));
        self::assertFalse($this->respond($this->listener(['Referrer-Policy' => 'origin'], 'reports', endpoint: new ReportEndpoint(uri: 'https://r.example.com/r')), Request::create('/page'))->headers->has('Reporting-Endpoints'));
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $excludedPaths
     */
    private function listener(array $values = [], ?string $group = null, array $excludedPaths = [], ?ReportEndpoint $endpoint = null): SecurityHeadersListener
    {
        return new SecurityHeadersListener(new SecurityHeaders($values, [], $group), $endpoint ?? new ReportEndpoint(), $group ?? 'csp', $excludedPaths);
    }

    private function respond(SecurityHeadersListener $listener, Request $request, ?Response $response = null, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $response ??= new Response();
        $listener->onKernelResponse(new ResponseEvent(new NullKernel(), $request, $type, $response));

        return $response;
    }
}
