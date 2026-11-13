<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Unit\Infrastructure;

use Phil\PwaBundle\Application\ManifestDocument;
use Phil\PwaBundle\Application\ServiceWorkerScript;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Domain\ValueObject\WorkerSettings;
use Phil\PwaBundle\Infrastructure\Asset\PackagesAssetUrls;
use Phil\PwaBundle\Infrastructure\Asset\PlainAssetUrls;
use Phil\PwaBundle\Infrastructure\ConfiguredPwa;
use Phil\PwaBundle\Infrastructure\Http\PwaController;
use Phil\PwaBundle\Infrastructure\Twig\PwaExtension;
use Phil\PwaBundle\Tests\Unit\Support\VersionedAssets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(PackagesAssetUrls::class)]
#[CoversClass(PlainAssetUrls::class)]
#[CoversClass(ConfiguredPwa::class)]
#[CoversClass(PwaController::class)]
#[CoversClass(PwaExtension::class)]
final class InfrastructureTest extends TestCase
{
    public function testAssetsAreAddressedByTheAssetComponentOrByTheirLogicalPath(): void
    {
        self::assertSame('/build/app.css?v2', new PackagesAssetUrls(new Packages(new Package(new StaticVersionStrategy('v2', '/build/%s?%s'))))->url('app.css'));
        self::assertSame('/app.css', new PlainAssetUrls()->url('app.css'));
        self::assertSame('/static/app.css', new PlainAssetUrls('/static/')->url('/app.css'));
    }

    public function testTheConfigurationIsReadOnce(): void
    {
        $configured = new ConfiguredPwa(['name' => 'Example'], ['cache' => 'site']);

        self::assertSame('Example', $configured->manifest()->name);
        self::assertSame('site', $configured->worker()->cache);
        self::assertSame($configured->manifest(), $configured->manifest());
    }

    public function testTheHeadLinksTheManifestAndTheThemeColourOnlyWhenThereIsOne(): void
    {
        $urls = self::urls();

        self::assertSame("<link rel=\"manifest\" href=\"/manifest.webmanifest\">\n<meta name=\"theme-color\" content=\"#1b1b1b\">", new PwaExtension(new Manifest('A', themeColor: '#1b1b1b'), $urls, '/')->head());
        self::assertSame('<link rel="manifest" href="/manifest.webmanifest">', new PwaExtension(new Manifest('A'), $urls, '/')->head());
        self::assertSame('/sw.js', new PwaExtension(new Manifest('A'), $urls, '/app/')->serviceWorkerPath());
        self::assertSame('/app/', new PwaExtension(new Manifest('A'), $urls, '/app/')->scope());
    }

    public function testTheHeadEscapesTheAddressOfTheManifest(): void
    {
        $urls = new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }

            /**
             * @param array<mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/a"b&c/manifest.webmanifest';
            }
        };

        self::assertSame('<link rel="manifest" href="/a&quot;b&amp;c/manifest.webmanifest">', new PwaExtension(new Manifest('A'), $urls, '/')->head());
    }

    public function testWithAMaxAgeOf0TheManifestAndTheOfflinePageAreCheckedEachTime(): void
    {
        $controller = self::controller(0, '/');

        self::assertSame('no-cache, private', $controller->manifest()->headers->get('Cache-Control'));
        self::assertSame('no-cache, private', $controller->offline()->headers->get('Cache-Control'));
        self::assertSame('max-age=5, public', self::controller(5, '/')->manifest()->headers->get('Cache-Control'));
    }

    public function testServiceWorkerAllowedIsSentOnlyForAScopeWiderThanTheAddress(): void
    {
        $at = static fn (string $scope, string $path, string $base = ''): ?string => self::controller(600, $scope)->serviceWorker(Request::create($path, server: ['SCRIPT_NAME' => $base.'/index.php', 'SCRIPT_FILENAME' => '/srv/index.php']))->headers->get('Service-Worker-Allowed');

        self::assertNull($at('/app/', '/app/sw.js', '/app'));
        self::assertSame('/', $at('/', '/pwa/sw.js', '/pwa'));
        self::assertSame('/apple/', $at('/apple/', '/app/sw.js'));
        self::assertNull($at('/', '/sw.js'));
        self::assertNull($at('/app/', '/sw.js'));
        self::assertNull($at('/app/', '/app/sw.js'));
        self::assertNull($at('/app/inner/', '/app/sw.js'));
        self::assertSame('/', $at('/', '/pwa/sw.js'));
        self::assertSame('/app/', $at('/app/', '/pwa/sw.js'));
    }

    private static function controller(int $maxAge, string $scope): PwaController
    {
        $manifest = new Manifest('A');

        return new PwaController(
            $manifest,
            new ManifestDocument(new VersionedAssets()),
            new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js'),
            $scope,
            new Environment(new ArrayLoader(['@PhilPwa/offline.html.twig' => '{{ layout }}|{{ manifest.name }}', 'layout' => ''])),
            self::urls(),
            'layout',
            $maxAge,
        );
    }

    private static function urls(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(PwaController::MANIFEST_ROUTE, new Route('/manifest.webmanifest'));
        $routes->add(PwaController::SERVICE_WORKER_ROUTE, new Route('/sw.js'));
        $routes->add(PwaController::OFFLINE_ROUTE, new Route('/offline'));

        return new UrlGenerator($routes, new RequestContext());
    }

    public function testTheResponsesHaveTheirMediaTypeAndTheirContent(): void
    {
        $controller = self::controller(600, '/');

        self::assertSame('application/manifest+json', $controller->manifest()->headers->get('Content-Type'));
        self::assertSame('text/javascript; charset=UTF-8', $controller->serviceWorker(Request::create('/sw.js'))->headers->get('Content-Type'));
        $offline = $controller->offline();
        self::assertSame('text/html; charset=UTF-8', $offline->headers->get('Content-Type'));
        self::assertSame('layout|A', $offline->getContent());
        self::assertSame('nosniff', $offline->headers->get('X-Content-Type-Options'));
    }

    public function testTheManifestAndTheOfflinePageAreKeptTenMinutesByDefault(): void
    {
        $controller = new PwaController(
            new Manifest('A'),
            new ManifestDocument(new VersionedAssets()),
            new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js'),
            '/',
            new Environment(new ArrayLoader(['@PhilPwa/offline.html.twig' => ''])),
            self::urls(),
            'layout',
        );

        self::assertSame('max-age=600, public', $controller->manifest()->headers->get('Cache-Control'));
    }

    public function testTheHeadEscapesTheSingleQuotesOfTheAddress(): void
    {
        $urls = self::createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn("/a'b/manifest.webmanifest");

        self::assertSame('<link rel="manifest" href="/a&apos;b/manifest.webmanifest">', new PwaExtension(new Manifest('A'), $urls, '/')->head());
    }
}
