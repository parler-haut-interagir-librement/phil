<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Functional;

use Override;
use Phil\PwaBundle\Infrastructure\Http\PwaController;
use Phil\PwaBundle\Infrastructure\Twig\PwaExtension;
use Phil\PwaBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PwaController::class)]
#[CoversClass(PwaExtension::class)]
final class PwaTest extends TestCase
{
    private ?TestKernel $kernel = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
    }

    public function testTheManifestIsGeneratedFromTheConfigurationWithItsAssetsVersioned(): void
    {
        $response = $this->get('/manifest.webmanifest');
        $manifest = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/manifest+json', $response->headers->get('Content-Type'));
        self::assertSame('max-age=600, public', $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame([], $response->headers->getCookies());
        self::assertIsArray($manifest);
        self::assertSame('Example', $manifest['name']);
        self::assertSame('fullscreen', $manifest['display']);
        self::assertIsArray($manifest['icons']);
        self::assertIsArray($manifest['icons'][0]);
        self::assertIsString($manifest['icons'][0]['src']);
        self::assertMatchesRegularExpression('#^/assets/images/icon-[\w-]+\.png$#', $manifest['icons'][0]['src']);
    }

    public function testTheServiceWorkerCarriesItsSettingsAndImportsItsCodeFromAssetMapper(): void
    {
        $response = $this->get('/sw.js');
        $body = (string) $response->getContent();

        self::assertSame('text/javascript; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertFalse($response->headers->has('Service-Worker-Allowed'));
        self::assertSame(1, preg_match('/^self\.philPwa = (\{.*\});$/m', $body, $settings));
        $data = json_decode($settings[1], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['precache']);
        self::assertSame('/offline', $data['precache'][0]);
        self::assertIsString($data['precache'][1]);
        self::assertMatchesRegularExpression('#^/assets/app-[\w-]+\.css$#', $data['precache'][1]);
        self::assertSame(['/', '/schedule.json'], \array_slice($data['precache'], 2));
        self::assertSame([['prefix' => '/assets/', 'strategy' => 'cache-first']], $data['rules']);
        self::assertSame(['/_profiler/', '/_wdt/'], $data['exclude']);
        self::assertIsString($data['script']);
        self::assertMatchesRegularExpression('#^/assets/@phil/pwa-bundle/service_worker-[\w-]+\.js$#', $data['script']);
        self::assertStringEndsWith('importScripts("'.$data['script'].'");'."\n", $body);

        $worker = $this->get($data['script']);
        self::assertSame(200, $worker->getStatusCode());
        self::assertInstanceOf(BinaryFileResponse::class, $worker);
        self::assertStringContainsString('function philPwaInstall(scope, settings)', (string) file_get_contents($worker->getFile()->getPathname()));
        self::assertStringStartsWith('text/javascript', (string) $worker->headers->get('Content-Type'));
    }

    public function testAWorkerServedBelowItsScopeAllowsIt(): void
    {
        $response = $this->get('/pwa/sw.js', '/pwa');

        self::assertSame('/', $response->headers->get('Service-Worker-Allowed'));
        self::assertStringContainsString('"offline":"/pwa/offline"', (string) $response->getContent());
    }

    public function testTheOfflinePageLinksToTheStartWithoutScript(): void
    {
        $response = $this->get('/offline');
        $page = new Crawler((string) $response->getContent());

        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('max-age=600, public', $response->headers->get('Cache-Control'));
        self::assertSame('Offline — Example', $page->filter('title')->text());
        self::assertSame('You are offline', $page->filter('h1')->text());
        self::assertSame('/', $page->filter('article a')->attr('href'));
        self::assertCount(0, $page->filter('script'));
    }

    public function testTheLayoutLinksTheManifestAndTheThemeColour(): void
    {
        $page = new Crawler((string) $this->get('/')->getContent());

        self::assertSame('/manifest.webmanifest', $page->filter('link[rel="manifest"]')->attr('href'));
        self::assertSame('#1b1b1b', $page->filter('meta[name="theme-color"]')->attr('content'));
        self::assertSame('/sw.js', $page->filter('p')->attr('data-worker'));
        self::assertSame('/', $page->filter('p')->attr('data-scope'));
    }

    private function get(string $path, string $prefix = ''): Response
    {
        $this->kernel?->shutdown();
        $this->kernel = new TestKernel('test', true, $prefix);

        return $this->kernel->handle(Request::create($path));
    }
}
