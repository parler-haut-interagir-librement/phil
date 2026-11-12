<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use Phil\BrowserSecurityBundle\Application\HeaderBuilder;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;
use Phil\BrowserSecurityBundle\Application\PolicyRegistry;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ContentSecurityPolicyListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ReportEndpoint;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\NullKernel;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\PreviewController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(ContentSecurityPolicyListener::class)]
#[CoversClass(ReportEndpoint::class)]
final class ContentSecurityPolicyListenerTest extends TestCase
{
    private HeaderBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new HeaderBuilder(PolicyRegistry::fromConfig([
            'default' => ['directives' => ['default-src' => ["'self'"]]],
            'preview' => ['directives' => ['default-src' => ["'none'"]]],
        ]));
    }

    public function testTheOverridesOfTheRouteThenOfTheController(): void
    {
        $request = Request::create('/preview');
        $request->attributes->set('_csp', ['add' => ['font-src' => ['data:']]]);

        $listener = new ContentSecurityPolicyListener($this->builder, new ReportEndpoint());

        $listener->onKernelController(new ControllerEvent(new NullKernel(), new PreviewController()->show(...), $request, HttpKernelInterface::MAIN_REQUEST));

        $fragment = Request::create('/fragment');
        $fragment->attributes->set('_csp', ['add' => ['font-src' => ['data:']]]);

        $listener->onKernelController(new ControllerEvent(new NullKernel(), new PreviewController()->show(...), $fragment, HttpKernelInterface::SUB_REQUEST));
        self::assertSame([], RequestState::of($fragment)->overrides);

        self::assertEquals([
            new PolicyOverride(add: ['font-src' => ['data:']]),
            new PolicyOverride(add: ['img-src' => ['data:']]),
            new PolicyOverride('preview'),
        ], RequestState::of($request)->overrides);
    }

    public function testAControllerWithoutAttribute(): void
    {
        $request = Request::create('/');
        new ContentSecurityPolicyListener($this->builder, new ReportEndpoint())->onKernelController(new ControllerEvent(new NullKernel(), static fn (): Response => new Response(), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame([], RequestState::of($request)->overrides);
    }

    public function testTheHeadersOfAResponse(): void
    {
        $request = Request::create('https://example.com/page');
        RequestState::of($request)->nonce(InlineKind::Script);
        $response = $this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint($this->urls())), $request);
        $nonce = RequestState::of($request)->nonce(InlineKind::Script);

        self::assertSame("default-src 'self'; script-src 'self' 'nonce-".$nonce."'; report-uri https://example.com/_csp/report; report-to csp", $response->headers->get(HeaderBuilder::ENFORCE));
        self::assertSame('csp="https://example.com/_csp/report"', $response->headers->get(HeaderBuilder::REPORTING_ENDPOINTS));
        self::assertSame([HeaderBuilder::ENFORCE, HeaderBuilder::REPORTING_ENDPOINTS], array_keys(RequestState::of($request)->headers));
    }

    public function testAResponseWithItsOwnPolicyKeepsIt(): void
    {
        foreach ([HeaderBuilder::ENFORCE, HeaderBuilder::REPORT_ONLY] as $header) {
            $request = Request::create('/page');
            $response = new Response('', Response::HTTP_OK, [$header => "default-src 'none'"]);
            $this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint()), $request, $response);

            self::assertSame("default-src 'none'", $response->headers->get($header));
            self::assertCount(1, array_filter([$response->headers->has(HeaderBuilder::ENFORCE), $response->headers->has(HeaderBuilder::REPORT_ONLY)]));
            self::assertSame([], RequestState::of($request)->headers);
        }
    }

    public function testAResponseWhosePolicyListsANonceIsPrivate(): void
    {
        $request = new Request();
        RequestState::of($request)->nonce(InlineKind::Script);
        $response = $this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint()), $request, new Response('', Response::HTTP_OK, ['Cache-Control' => 'public, max-age=600']));

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testTheDefaultCacheControlOfANoncedResponseStays(): void
    {
        $request = new Request();
        RequestState::of($request)->nonce(InlineKind::Script);
        $response = $this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint()), $request);

        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
    }

    public function testAResponseWithoutNonceKeepsItsCacheControl(): void
    {
        $response = $this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint()), new Request(), new Response('', Response::HTTP_OK, ['Cache-Control' => 'public, max-age=600']));

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
    }

    public function testAPolicyWrittenByTheControllerWithANonceIsPrivateToo(): void
    {
        foreach ([HeaderBuilder::ENFORCE, HeaderBuilder::REPORT_ONLY] as $header) {
            $response = new Response('', Response::HTTP_OK, [$header => "script-src 'nonce-abc'", 'Cache-Control' => 'public, max-age=600']);
            $this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint()), new Request(), $response);

            self::assertTrue($response->headers->hasCacheControlDirective('private'), $header);
        }
    }

    public function testASubRequestAndAnExcludedPathGetNothing(): void
    {
        $listener = new ContentSecurityPolicyListener($this->builder, new ReportEndpoint(), excludedPaths: ['/_wdt', '/_profiler']);

        self::assertFalse($this->respond($listener, Request::create('/page'), type: HttpKernelInterface::SUB_REQUEST)->headers->has(HeaderBuilder::ENFORCE));
        self::assertFalse($this->respond($listener, Request::create('/_profiler/abc'))->headers->has(HeaderBuilder::ENFORCE));
        self::assertFalse($this->respond($listener, Request::create('/_wdt/abc'))->headers->has(HeaderBuilder::ENFORCE));
        self::assertTrue($this->respond($listener, Request::create('/x/_wdt'))->headers->has(HeaderBuilder::ENFORCE));
    }

    public function testTheAddressOfTheReports(): void
    {
        $request = Request::create('https://example.com/page');
        $header = static fn (Response $response): string => (string) $response->headers->get(HeaderBuilder::ENFORCE);

        self::assertSame("default-src 'self'", $header($this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint($this->urls(), false)), $request)));
        self::assertSame("default-src 'self'", $header($this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint()), Request::create('/page'))));
        self::assertSame("default-src 'self'", $header($this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint(new UrlGenerator(new RouteCollection(), new RequestContext()))), Request::create('/page'))));
        self::assertStringEndsWith('report-uri https://example.com/csp; report-to csp', $header($this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint($this->urls(), true, '/csp')), Request::create('https://example.com/page'))));
        self::assertStringEndsWith('report-uri https://reports.example.net/r; report-to csp', $header($this->respond(new ContentSecurityPolicyListener($this->builder, new ReportEndpoint(null, true, 'https://reports.example.net/r')), Request::create('/page'))));
    }

    private function respond(ContentSecurityPolicyListener $listener, Request $request, ?Response $response = null, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $response ??= new Response();
        $listener->onKernelResponse(new ResponseEvent(new NullKernel(), $request, $type, $response));

        return $response;
    }

    private function urls(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(ContentSecurityPolicyListener::REPORT_ROUTE, new Route('/_csp/report'));

        return new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https'));
    }
}
