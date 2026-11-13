<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Unit\Application;

use Phil\PwaBundle\Application\ManifestReader;
use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Phil\PwaBundle\Domain\Manifest\CacheStrategy;
use Phil\PwaBundle\Domain\Manifest\DisplayMode;
use Phil\PwaBundle\Domain\Manifest\IconPurpose;
use Phil\PwaBundle\Domain\Manifest\Orientation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManifestReader::class)]
final class ManifestReaderTest extends TestCase
{
    public function testItReadsEveryMember(): void
    {
        $manifest = new ManifestReader()->read([
            'name' => 'Example',
            'short_name' => 'Ex',
            'description' => 'A site',
            'id' => '/app/',
            'start_url' => '/app/?source=pwa',
            'scope' => '/app/',
            'display' => 'fullscreen',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'orientation' => 'portrait',
            'theme_color' => '#1b1b1b',
            'background_color' => '#ffffff',
            'lang' => 'fr',
            'dir' => 'ltr',
            'categories' => ['events'],
            'icons' => [
                ['src' => 'images/icon.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => ['maskable']],
                ['src' => '/favicon.svg', 'sizes' => null, 'type' => null, 'purpose' => []],
            ],
            'shortcuts' => [['name' => 'Schedule', 'url' => '/app/schedule', 'short_name' => 'Sched', 'description' => 'The programme']],
        ]);

        self::assertSame('Example', $manifest->name);
        self::assertSame('Ex', $manifest->shortName);
        self::assertSame('A site', $manifest->description);
        self::assertSame('/app/', $manifest->id);
        self::assertSame('/app/?source=pwa', $manifest->startUrl);
        self::assertSame('/app/', $manifest->scope);
        self::assertSame(DisplayMode::Fullscreen, $manifest->display);
        self::assertSame([DisplayMode::WindowControlsOverlay, DisplayMode::Standalone], $manifest->displayOverride);
        self::assertSame(Orientation::Portrait, $manifest->orientation);
        self::assertSame('#1b1b1b', $manifest->themeColor);
        self::assertSame('#ffffff', $manifest->backgroundColor);
        self::assertSame('fr', $manifest->lang);
        self::assertSame('ltr', $manifest->dir);
        self::assertSame(['events'], $manifest->categories);
        self::assertSame('images/icon.png', $manifest->icons[0]->src);
        self::assertSame('192x192', $manifest->icons[0]->sizes);
        self::assertSame('image/png', $manifest->icons[0]->type);
        self::assertSame([IconPurpose::Maskable], $manifest->icons[0]->purposes);
        self::assertSame('any', $manifest->icons[1]->sizes);
        self::assertNull($manifest->icons[1]->type);
        self::assertSame([IconPurpose::Any], $manifest->icons[1]->purposes);
        self::assertSame('Schedule', $manifest->shortcuts[0]->name);
        self::assertSame('/app/schedule', $manifest->shortcuts[0]->url);
        self::assertSame('Sched', $manifest->shortcuts[0]->shortName);
        self::assertSame('The programme', $manifest->shortcuts[0]->description);
    }

    public function testANameIsEnough(): void
    {
        $manifest = new ManifestReader()->read(['name' => 'Example', 'short_name' => null, 'orientation' => null]);

        self::assertSame('/', $manifest->startUrl);
        self::assertSame('/', $manifest->id);
        self::assertSame('/', $manifest->scope);
        self::assertSame(DisplayMode::Standalone, $manifest->display);
        self::assertNull($manifest->orientation);
        self::assertNull($manifest->shortName);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function refusedManifests(): iterable
    {
        yield 'unknown key' => [['name' => 'A', 'color' => 'red'], 'The keys of the manifest are name, short_name, description, id, start_url, scope, display, display_override, orientation, theme_color, background_color, lang, dir, icons, shortcuts, categories.'];
        yield 'no name' => [[], 'The name of the manifest is a string.'];
        yield 'name not a string' => [['name' => 3], 'The name of the manifest is a string.'];
        yield 'start not a string' => [['name' => 'A', 'start_url' => ['/']], 'The start_url of the manifest is a string.'];
        yield 'unknown display' => [['name' => 'A', 'display' => 'kiosk'], 'A display mode is "fullscreen", "standalone", "minimal-ui", "browser", "window-controls-overlay" or "tabbed".'];
        yield 'unknown override' => [['name' => 'A', 'display_override' => ['kiosk']], 'A display mode is'];
        yield 'override not a list' => [['name' => 'A', 'display_override' => 'fullscreen'], 'The display_override of the manifest are a list of 20 strings at most.'];
        yield 'override a map' => [['name' => 'A', 'display_override' => ['a' => 'fullscreen']], 'The display_override of the manifest are a list'];
        yield 'override of integers' => [['name' => 'A', 'display_override' => [1]], 'The display_override of the manifest are a list'];
        yield 'too many categories' => [['name' => 'A', 'categories' => array_fill(0, 21, 'a')], 'The categories of the manifest are a list of 20 strings at most.'];
        yield 'unknown orientation' => [['name' => 'A', 'orientation' => 'sideways'], 'The orientation is one of the orientations of the Screen Orientation API, such as "portrait".'];
        yield 'icons not a list' => [['name' => 'A', 'icons' => 'icon.png'], 'The icons are a list of 30 maps at most.'];
        yield 'icons a map' => [['name' => 'A', 'icons' => ['a' => ['src' => '/i.png']]], 'The icons are a list of 30 maps at most.'];
        yield 'icon not a map' => [['name' => 'A', 'icons' => ['/i.png']], 'The icons are a list of 30 maps at most.'];
        yield 'too many icons' => [['name' => 'A', 'icons' => array_fill(0, 31, ['src' => '/i.png'])], 'The icons are a list of 30 maps at most.'];
        yield 'unknown icon key' => [['name' => 'A', 'icons' => [['src' => '/i.png', 'alt' => 'x']]], 'The keys of the icon 1 are src, sizes, type, purpose.'];
        yield 'icon without source' => [['name' => 'A', 'icons' => [['sizes' => 'any']]], 'The src of the icon 1 is a string.'];
        yield 'unknown purpose' => [['name' => 'A', 'icons' => [['src' => '/i.png', 'purpose' => ['badge']]]], 'The purpose of icon 1 is "any", "maskable" or "monochrome".'];
        yield 'too many shortcuts' => [['name' => 'A', 'shortcuts' => array_fill(0, 11, ['name' => 'S', 'url' => '/s'])], 'The shortcuts are a list of 10 maps at most.'];
        yield 'unknown shortcut key' => [['name' => 'A', 'shortcuts' => [['name' => 'S', 'url' => '/s', 'icons' => []]]], 'The keys of the shortcut 1 are name, url, short_name, description.'];
        yield 'shortcut without address' => [['name' => 'A', 'shortcuts' => [['name' => 'S']]], 'The url of the shortcut 1 is a string.'];
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('refusedManifests')]
    public function testItRefusesTheManifest(array $data, string $message): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ManifestReader()->read($data);
    }

    public function testItReadsTheSettingsOfTheWorker(): void
    {
        $settings = new ManifestReader()->worker([
            'cache' => 'site',
            'scope' => '/app/',
            'cache_rules' => [['prefix' => '/app/assets/', 'strategy' => 'cache-first'], ['prefix' => '/app/api/', 'strategy' => 'network-first']],
            'exclude' => ['/app/admin/'],
            'offline' => false,
        ]);

        self::assertSame('site', $settings->cache);
        self::assertSame('/app/', $settings->scope);
        self::assertSame('/app/assets/', $settings->rules[0]->prefix);
        self::assertSame(CacheStrategy::CacheFirst, $settings->rules[0]->strategy);
        self::assertSame(CacheStrategy::NetworkFirst, $settings->rules[1]->strategy);
        self::assertSame(['/app/admin/'], $settings->exclude);
        self::assertFalse($settings->offline);

        $defaults = new ManifestReader()->worker([]);
        self::assertSame('phil-pwa', $defaults->cache);
        self::assertSame('/', $defaults->scope);
        self::assertTrue($defaults->offline);
        self::assertSame([], $defaults->rules);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function refusedWorkers(): iterable
    {
        yield 'unknown key' => [['strategy' => 'x'], 'The keys of the service worker are cache, scope, cache_rules, exclude, offline.'];
        yield 'offline not a boolean' => [['offline' => 'yes'], 'The offline setting of the service worker is a boolean.'];
        yield 'too many rules' => [['cache_rules' => array_fill(0, 21, ['prefix' => '/a/', 'strategy' => 'cache-first'])], 'The cache_rules are a list of 20 maps at most.'];
        yield 'unknown rule key' => [['cache_rules' => [['prefix' => '/a/', 'strategy' => 'cache-first', 'ttl' => 3]]], 'The keys of the cache rule 1 are prefix, strategy.'];
        yield 'unknown strategy' => [['cache_rules' => [['prefix' => '/a/', 'strategy' => 'stale']]], 'The strategy of cache rule 1 is "network-first", "cache-first" or "network-only".'];
        yield 'rule without prefix' => [['cache_rules' => [['strategy' => 'cache-first']]], 'The prefix of the cache rule 1 is a string.'];
        yield 'cache not a string' => [['cache' => 1], 'The cache of the service worker is a string.'];
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('refusedWorkers')]
    public function testItRefusesTheWorker(array $data, string $message): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ManifestReader()->worker($data);
    }

    public function testTheLargestListsAreRead(): void
    {
        $manifest = new ManifestReader()->read([
            'name' => 'A',
            'icons' => array_fill(0, 30, ['src' => '/i.png']),
            'shortcuts' => array_fill(0, 10, ['name' => 'S', 'url' => '/s']),
            'display_override' => array_fill(0, 1, 'tabbed'),
        ]);

        self::assertCount(30, $manifest->icons);
        self::assertCount(10, $manifest->shortcuts);
        self::assertCount(20, new ManifestReader()->worker(['exclude' => array_fill(0, 20, '/a/')])->exclude);
    }
}
