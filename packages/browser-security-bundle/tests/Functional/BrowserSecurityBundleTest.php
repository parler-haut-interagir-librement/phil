<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\BrowserSecurityBundle\Domain\Contract\ReportRepository;
use Phil\BrowserSecurityBundle\Infrastructure\Doctrine\ReportTable;
use Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle;
use Phil\BrowserSecurityBundle\Tests\Functional\App\RecordedViolations;
use Phil\BrowserSecurityBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The bundle in a kernel of its own, each request served as a worker would serve it: handled, terminated, then the
 * services reset.
 */
#[CoversNothing]
final class BrowserSecurityBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $schemaManager = $connection->createSchemaManager();

        if ($schemaManager->tablesExist([ReportTable::NAME])) {
            $schemaManager->dropTable(ReportTable::NAME);
        }

        foreach (ReportTable::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $pool = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
    }

    public function testAPageGetsItsNoncesInTheHeaderAndTheTags(): void
    {
        $response = $this->request('GET', 'https://example.com/page');
        $header = (string) $response->headers->get('Content-Security-Policy');
        $content = (string) $response->getContent();

        self::assertSame(1, preg_match("/script-src 'self' 'nonce-([A-Za-z0-9+\\/]+=*)' 'strict-dynamic'/", $header, $script));
        self::assertSame(1, preg_match("/style-src 'self' 'nonce-([A-Za-z0-9+\\/]+=*)' 'nonce-([A-Za-z0-9+\\/]+=*)'/", $header, $style));
        self::assertStringContainsString('<meta name="csp-nonce" content="'.$script[1].'">', $content);
        self::assertStringContainsString('<script type="importmap" nonce="'.$script[1].'">', $content);
        self::assertStringContainsString('<script nonce="'.$script[1].'">console.log', $content);
        self::assertStringContainsString('<style nonce="'.$style[1].'">', $content);
        self::assertSame($script[1], $style[2]);
        self::assertStringContainsString("object-src 'none'", $header);
        self::assertStringEndsWith('report-uri https://example.com/_csp/report; report-to csp', $header);
        self::assertSame('csp="https://example.com/_csp/report"', $response->headers->get('Reporting-Endpoints'));
        self::assertSame('camera=()', $response->headers->get('Permissions-Policy'));
        self::assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));

        $again = (string) $this->request('GET', 'https://example.com/page')->headers->get('Content-Security-Policy');
        self::assertStringNotContainsString($script[1], $again, 'a worker gives each request its own nonce');
    }

    public function testTheStructuredDataOfAPageAsksForNoNonceAndStaysInSharedCaches(): void
    {
        $response = $this->request('GET', '/described');

        self::assertStringContainsString('<script type="application/ld+json">', (string) $response->getContent());
        self::assertStringNotContainsString("'nonce-", (string) $response->headers->get('Content-Security-Policy'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
    }

    public function testAStructuredDataGivenItsNonceByTheTemplateMakesThePagePrivate(): void
    {
        $response = $this->request('GET', '/described-by-hand');
        $header = (string) $response->headers->get('Content-Security-Policy');

        self::assertSame(1, preg_match('/<script type="application\/ld\+json" nonce="([^"]+)">/', (string) $response->getContent(), $element));
        self::assertStringContainsString("'nonce-".$element[1]."'", $header);
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testProductionRefusesAPoolLocalToEachServer(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_browser_security.reporting.rate_limit.cache_pool: the store "cache.app" is local to each server');

        new TestKernel('prod', false)->boot();
    }

    public function testTheAttributeOfAController(): void
    {
        self::assertSame("default-src 'none'; style-src 'unsafe-inline'; sandbox; frame-ancestors 'self'", $this->request('GET', '/preview')->headers->get('Content-Security-Policy'));
    }

    public function testTheOverrideOfARoute(): void
    {
        self::assertSame("default-src 'none'", $this->request('GET', '/own')->headers->get('Content-Security-Policy'));
        self::assertStringContainsString("img-src 'self' https://images.example.com", (string) $this->request('GET', '/images')->headers->get('Content-Security-Policy'), 'the route default applies when the controller wrote no policy');

        $relaxed = $this->request('GET', '/relaxed');
        self::assertFalse($relaxed->headers->has('Content-Security-Policy'));
        self::assertSame("default-src 'none'; style-src 'unsafe-inline'; sandbox; frame-ancestors 'self'", $relaxed->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function testUserContentByRouteByAttributeAndByResponse(): void
    {
        $policy = "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; sandbox";
        $upload = $this->request('GET', '/upload');
        self::assertSame($policy, $upload->headers->get('Content-Security-Policy'));
        self::assertSame('attachment', $upload->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $upload->headers->get('X-Content-Type-Options'));
        self::assertSame('same-origin', $upload->headers->get('Cross-Origin-Resource-Policy'));
        self::assertSame('DENY', $upload->headers->get('X-Frame-Options'), 'the global SAMEORIGIN gives way');
        self::assertSame('no-referrer', $upload->headers->get('Referrer-Policy'));
        self::assertFalse($upload->headers->has('Content-Security-Policy-Report-Only'));

        $picture = $this->request('GET', '/picture');
        self::assertSame($policy, $picture->headers->get('Content-Security-Policy'));
        self::assertSame('inline', $picture->headers->get('Content-Disposition'));

        $served = $this->request('GET', '/served');
        self::assertSame($policy, $served->headers->get('Content-Security-Policy'));
        self::assertSame('inline; filename=notes.txt', $served->headers->get('Content-Disposition'));
        self::assertNotSame($policy, $this->request('GET', '/page')->headers->get('Content-Security-Policy'));
    }

    public function testInlineCodeAllowedFromAService(): void
    {
        self::assertStringContainsString("script-src 'self' 'sha256-", (string) $this->request('GET', '/inline')->headers->get('Content-Security-Policy'));
    }

    public function testTheEndpointKeepsAndAnnouncesTheReports(): void
    {
        $body = '{"csp-report":{"document-uri":"https://example.com/page?token=1","effective-directive":"script-src-elem","blocked-uri":"inline"}}';

        self::assertSame(204, $this->request('POST', '/_csp/report', $body, 'application/csp-report')->getStatusCode());
        self::assertSame(204, $this->request('POST', '/_csp/report', $body, 'application/csp-report')->getStatusCode());
        self::assertSame(415, $this->request('POST', '/_csp/report', $body, 'text/plain')->getStatusCode());
        self::assertSame(429, $this->request('POST', '/_csp/report', $body, 'application/csp-report')->getStatusCode());
        self::assertSame(405, $this->request('GET', '/_csp/report')->getStatusCode());

        $repository = self::getContainer()->get(ReportRepository::class);
        self::assertInstanceOf(ReportRepository::class, $repository);
        $latest = $repository->latest(10);
        self::assertCount(1, $latest);
        self::assertSame(2, $latest[0]->count);
        self::assertSame('https://example.com/page', $latest[0]->report->documentUri);
        self::assertSame('192.0.2.0', $latest[0]->client);
        $events = self::getContainer()->get(RecordedViolations::class);
        self::assertInstanceOf(RecordedViolations::class, $events);
        self::assertCount(1, $events->events, 'the second report falls in the window of deduplication');
        self::assertSame('192.0.2.0', $events->events[0]->client);
    }

    public function testTheDefaultPolicy(): void
    {
        self::assertSame("'self'", PhilBrowserSecurityBundle::DEFAULT_POLICY['default-src'][0]);
    }

    public function testTheHeadersOfAResponse(): void
    {
        $headers = $this->page('/images', secure: true)->headers;

        self::assertSame('SAMEORIGIN', $headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('same-site', $headers->get('Cross-Origin-Resource-Policy'));
        self::assertSame('none', $headers->get('X-Permitted-Cross-Domain-Policies'));
        self::assertSame('?1', $headers->get('Origin-Agent-Cluster'));
        self::assertSame('max-age=31536000', $headers->get('Strict-Transport-Security'));
        self::assertStringStartsWith('same-origin; report-to="csp"', (string) $headers->get('Cross-Origin-Opener-Policy-Report-Only'));
        self::assertSame('csp="https://localhost/_csp/report"', $headers->get('Reporting-Endpoints'));
    }

    public function testAPageAdjustsItsHeaders(): void
    {
        self::assertFalse($this->page('/embed/map')->headers->has('X-Frame-Options'));
        self::assertFalse($this->page('/images')->headers->has('Strict-Transport-Security'));

        $strict = $this->page('/strict')->headers;
        self::assertSame('DENY', $strict->get('X-Frame-Options'));
        self::assertFalse($strict->has('Referrer-Policy'));
        self::assertFalse($this->page('/no-policy')->headers->has('Content-Security-Policy'));
    }

    public function testSignedCookies(): void
    {
        $response = $this->page('/cookie');
        $cookie = $response->headers->getCookies()[0];
        self::assertSame('none', $response->getContent());
        self::assertStringStartsWith('dark.', (string) $cookie->getValue());

        self::assertSame('dark', $this->page('/cookie', cookies: ['theme' => (string) $cookie->getValue()])->getContent());
        self::assertSame('none', $this->page('/cookie', cookies: ['theme' => 'light.'.substr((string) $cookie->getValue(), 5)])->getContent());
    }

    public function testAnExternalRedirectIsRefusedUnlessThePageAllowsIt(): void
    {
        self::assertSame(403, $this->page('/leave')->getStatusCode());
        self::assertSame(302, $this->page('/leave-allowed')->getStatusCode());
    }

    public function testARouteCanForceHttps(): void
    {
        $response = $this->page('/forced?a=1');

        self::assertSame(308, $response->getStatusCode());
        self::assertSame('https://localhost/forced?a=1', $response->headers->get('Location'));
        self::assertSame(200, $this->page('/images')->getStatusCode());
    }

    public function testEveryFirewallClearsTheSiteDataAtLogout(): void
    {
        $dispatcher = self::getContainer()->get('security.event_dispatcher.main');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $event = new LogoutEvent(new Request(), null);
        $event->setResponse(new Response());

        $dispatcher->dispatch($event);

        self::assertSame('"cookies", "storage"', $event->getResponse()?->headers->get('Clear-Site-Data'));
    }

    /**
     * @param array<string, string> $cookies
     */
    private function page(string $uri, bool $secure = false, array $cookies = []): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        return $kernel->handle(Request::create(($secure ? 'https://localhost' : 'http://localhost').$uri, cookies: $cookies));
    }

    private function request(string $method, string $uri, string $body = '', string $type = ''): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $request = Request::create($uri, $method, server: ['REMOTE_ADDR' => '192.0.2.77', 'CONTENT_TYPE' => $type], content: $body);
        $response = $kernel->handle($request);

        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }

        $resetter = self::getContainer()->get('services_resetter');
        self::assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        return $response;
    }
}
