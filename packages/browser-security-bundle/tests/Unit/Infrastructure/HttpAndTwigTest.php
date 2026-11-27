<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Infrastructure;

use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Application\HeaderBuilder;
use Phil\BrowserSecurityBundle\Application\PolicyRegistry;
use Phil\BrowserSecurityBundle\Application\ReportParser;
use Phil\BrowserSecurityBundle\Application\ReportReceiver;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ReportController;
use Phil\BrowserSecurityBundle\Infrastructure\Twig\CspExtension;
use Phil\BrowserSecurityBundle\Infrastructure\Twig\NoncedImportMapRuntime;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\MemoryReports;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(ReportController::class)]
#[CoversClass(CspExtension::class)]
#[CoversClass(NoncedImportMapRuntime::class)]
final class HttpAndTwigTest extends TestCase
{
    private function legacy(): HeaderBuilder
    {
        return new HeaderBuilder(PolicyRegistry::fromConfig(['default' => ['nonces' => false]]));
    }

    public function testTheEndpointAnswersWithTheReceipt(): void
    {
        $reports = new MemoryReports();
        $controller = new ReportController(new ReportReceiver(new ReportParser(), new MockClock(), 'secret', 200, repository: $reports));
        $body = '{"csp-report":{"document-uri":"https://example.com/","effective-directive":"img-src"}}';

        $response = $controller($this->post('application/csp-report', $body));
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertCount(1, $reports->recorded);

        self::assertSame(415, $controller($this->post('text/plain', $body))->getStatusCode());
        self::assertSame(415, $controller(Request::create('/_csp/report', 'POST', content: $body))->getStatusCode());
        self::assertSame(413, $controller($this->post('application/csp-report', str_pad($body, 201)))->getStatusCode());
        self::assertSame(204, $controller($this->post('application/csp-report', str_pad($body, 200)))->getStatusCode());
    }

    public function testTheTwigFunctions(): void
    {
        $request = new Request();
        $requests = new RequestStack([$request]);
        $twig = new Environment(new ArrayLoader([
            'page' => '{{ csp_meta() }}|{{ csp_nonce() }}|{{ csp_nonce("style") }}',
        ]), ['autoescape' => 'html']);
        $twig->addExtension(new CspExtension(new ContentSecurityPolicyContext($requests)));

        $state = RequestState::of($request);

        self::assertSame(
            '<meta name="csp-nonce" content="'.$state->nonce(InlineKind::Script).'">|'.$state->nonce(InlineKind::Script).'|'.$state->nonce(InlineKind::Style),
            $twig->render('page'),
        );
        self::assertTrue($state->sharedNonce);
    }

    public function testTheTwigFunctionsUnderAPolicyWithoutNonces(): void
    {
        $request = new Request();
        $requests = new RequestStack([$request]);
        $twig = new Environment(new ArrayLoader([
            'page' => '[{{ csp_meta() }}]{{ csp_nonce() is null ? "none" : "nonce" }}|{{ csp_nonce("style") is null ? "none" : "nonce" }}',
        ]), ['autoescape' => 'html', 'strict_variables' => true]);
        $twig->addExtension(new CspExtension(new ContentSecurityPolicyContext($requests, $this->legacy())));

        self::assertSame('[]none|none', $twig->render('page'));
        self::assertSame([], RequestState::of($request)->nonces());
        self::assertFalse(RequestState::of($request)->sharedNonce);
    }

    public function testImportmapUnderAPolicyWithoutNonces(): void
    {
        $request = new Request();
        $generator = self::createStub(ImportMapGenerator::class);
        $generator->method('getImportMapData')->willReturn([]);
        $runtime = NoncedImportMapRuntime::create(new ImportMapRenderer($generator), new ContentSecurityPolicyContext(new RequestStack([$request]), $this->legacy()));

        self::assertStringContainsString('<script type="importmap">', $runtime->importmap());
        self::assertStringNotContainsString('nonce', $runtime->importmap('app'));
        self::assertStringContainsString('<script type="importmap" nonce="mine">', $runtime->importmap(['app'], ['nonce' => 'mine']));
        self::assertSame([], RequestState::of($request)->nonces());
    }

    public function testImportmapGetsTheScriptNonce(): void
    {
        $request = new Request();
        $requests = new RequestStack([$request]);
        $generator = self::createStub(ImportMapGenerator::class);
        $generator->method('getImportMapData')->willReturn([]);
        $runtime = NoncedImportMapRuntime::create(new ImportMapRenderer($generator), new ContentSecurityPolicyContext($requests));
        $nonce = RequestState::of($request)->nonce(InlineKind::Script);

        self::assertStringContainsString('<script type="importmap" nonce="'.$nonce.'">', $runtime->importmap());
        self::assertStringContainsString('<script type="module" nonce="'.$nonce.'">', $runtime->importmap('app'));
        self::assertStringContainsString('<script type="importmap" nonce="mine" data-x="1">', $runtime->importmap(['app'], ['nonce' => 'mine', 'data-x' => '1']));
    }

    private function post(string $type, string $body): Request
    {
        return Request::create('/_csp/report', 'POST', server: ['CONTENT_TYPE' => $type, 'REMOTE_ADDR' => '192.0.2.10'], content: $body);
    }
}
