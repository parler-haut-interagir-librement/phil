<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Unit\Application;

use Phil\PwaBundle\Application\ManifestDocument;
use Phil\PwaBundle\Application\ServiceWorkerScript;
use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Phil\PwaBundle\Domain\Manifest\CacheStrategy;
use Phil\PwaBundle\Domain\Manifest\DisplayMode;
use Phil\PwaBundle\Domain\Manifest\IconPurpose;
use Phil\PwaBundle\Domain\Manifest\Orientation;
use Phil\PwaBundle\Domain\ValueObject\CacheRule;
use Phil\PwaBundle\Domain\ValueObject\Icon;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Domain\ValueObject\Shortcut;
use Phil\PwaBundle\Domain\ValueObject\WorkerSettings;
use Phil\PwaBundle\Tests\Unit\Support\ListedPaths;
use Phil\PwaBundle\Tests\Unit\Support\VersionedAssets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManifestDocument::class)]
#[CoversClass(ServiceWorkerScript::class)]
final class ScriptsTest extends TestCase
{
    public function testTheManifestIsTheJsonOfItsMembersWithTheAddressesOfItsAssets(): void
    {
        $manifest = new Manifest(
            'Événement',
            'Évén.',
            'Le programme',
            '/app/',
            '/app/?source=pwa',
            '/app/',
            DisplayMode::Fullscreen,
            [DisplayMode::WindowControlsOverlay],
            Orientation::Landscape,
            '#1b1b1b',
            '#fff',
            'fr-CA',
            'ltr',
            [new Icon('images/icon.png', '192x192', 'image/png', [IconPurpose::Any, IconPurpose::Maskable]), new Icon('/favicon.svg')],
            [new Shortcut('Horaire', '/app/horaire', 'Hor.', 'Mon horaire'), new Shortcut('Carte', '/app/carte')],
            ['events'],
        );

        $json = new ManifestDocument(new VersionedAssets())->render($manifest);

        self::assertStringEndsWith("}\n", $json);
        self::assertStringContainsString('"name": "Événement"', $json);
        self::assertStringContainsString('"start_url": "/app/?source=pwa"', $json);
        self::assertSame([
            'name' => 'Événement',
            'short_name' => 'Évén.',
            'description' => 'Le programme',
            'id' => '/app/',
            'start_url' => '/app/?source=pwa',
            'scope' => '/app/',
            'display' => 'fullscreen',
            'display_override' => ['window-controls-overlay'],
            'orientation' => 'landscape',
            'theme_color' => '#1b1b1b',
            'background_color' => '#fff',
            'lang' => 'fr-CA',
            'dir' => 'ltr',
            'icons' => [
                ['src' => '/assets/images/icon-v1.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/favicon.svg', 'sizes' => 'any', 'purpose' => 'any'],
            ],
            'shortcuts' => [
                ['name' => 'Horaire', 'short_name' => 'Hor.', 'description' => 'Mon horaire', 'url' => '/app/horaire'],
                ['name' => 'Carte', 'url' => '/app/carte'],
            ],
            'categories' => ['events'],
        ], json_decode($json, true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testAManifestWithItsDefaultsLeavesOutWhatIsNotGiven(): void
    {
        self::assertSame(
            ['name' => 'A', 'id' => '/', 'start_url' => '/', 'scope' => '/', 'display' => 'standalone'],
            json_decode(new ManifestDocument(new VersionedAssets())->render(new Manifest('A')), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testTheWorkerIsToldItsSettingsAndPrecachesEachPathOnce(): void
    {
        $script = new ServiceWorkerScript(
            new WorkerSettings('site', '/', [new CacheRule('/assets/', CacheStrategy::CacheFirst)], ['/_profiler/']),
            new VersionedAssets(),
            '@phil/pwa-bundle/service_worker.js',
            ['styles/app.css', '/', '/offline', '/./'],
            [new ListedPaths(['/screens/hall', '/']), new ListedPaths(['/schedule.json'])],
        );

        $settings = $script->settings('/offline');

        self::assertSame([
            'version' => $settings['version'],
            'cache' => 'site',
            'scope' => '/',
            'precache' => ['/offline', '/assets/styles/app-v1.css', '/', '/screens/hall', '/schedule.json'],
            'offline' => '/offline',
            'rules' => [['prefix' => '/assets/', 'strategy' => 'cache-first']],
            'exclude' => ['/_profiler/'],
            'script' => '/assets/@phil/pwa-bundle/service_worker-v1.js',
        ], $settings);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/D', $settings['version']);
    }

    public function testTheVersionChangesWithTheSettingsOnly(): void
    {
        $version = static fn (array $precache, ?string $offline = '/offline'): string => new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js', $precache)->settings($offline)['version'];

        self::assertSame($version(['/a']), $version(['/a']));
        self::assertNotSame($version(['/a']), $version(['/b']));
        self::assertNotSame($version(['/a']), $version(['/a'], '/hors-ligne'));
        self::assertSame(substr(hash('xxh128', '{"cache":"phil-pwa","scope":"/","precache":["/offline","/a"],"offline":"/offline","rules":[],"exclude":[],"script":"/assets/sw-v1.js"}'), 0, 16), $version(['/a']));
    }

    public function testWithoutTheOfflinePageNothingIsAddedForIt(): void
    {
        $settings = new ServiceWorkerScript(new WorkerSettings(offline: false), new VersionedAssets(), 'sw.js', ['/a'])->settings('/offline');

        self::assertSame(['/a'], $settings['precache']);
        self::assertNull($settings['offline']);
        self::assertSame([], new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js')->settings(null)['precache']);
    }

    public function testTheScriptSetsTheSettingsThenImportsTheWorker(): void
    {
        $script = new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js', ["/a'<b>&\""]);
        $body = $script->render('/offline');

        self::assertStringStartsWith("// The service worker of phil/pwa-bundle: its settings, then its code.\nself.philPwa = {\"version\":\"", $body);
        self::assertStringEndsWith(";\nimportScripts(\"/assets/sw-v1.js\");\n", $body);
        self::assertStringContainsString('"precache":["/offline","/a\'%3Cb%3E&%22"]', $body);
    }

    public function testAProviderPathThatIsNotLocalIsRefused(): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The path of a precache provider is a path of the site from its root');

        new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js', [], [new ListedPaths(['https://evil.example/'])])->settings(null);
    }

    public function testAConfiguredPathThatIsNotLocalIsRefused(): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The precached path is a path of the site from its root, or the logical path of an asset');

        new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js', ['../secret'])->settings(null);
    }

    public function testTheScriptOfTheWorkerIsTheLogicalPathOfAnAsset(): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The script of the service worker is a path of the site from its root, or the logical path of an asset');

        new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), '../sw.js')->settings(null);
    }

    public function testThePrecacheListHoldsTwoHundredPathsAtMost(): void
    {
        $paths = array_map(static fn (int $i): string => '/p'.$i, range(1, 200));
        self::assertCount(200, new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js', $paths)->settings(null)['precache']);

        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The service worker precaches 200 paths at most.');
        new ServiceWorkerScript(new WorkerSettings(), new VersionedAssets(), 'sw.js', $paths)->settings('/offline');
    }
}
