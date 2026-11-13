<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Unit\Domain;

use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Phil\PwaBundle\Domain\Manifest\CacheStrategy;
use Phil\PwaBundle\Domain\Manifest\DisplayMode;
use Phil\PwaBundle\Domain\Manifest\IconPurpose;
use Phil\PwaBundle\Domain\Manifest\Orientation;
use Phil\PwaBundle\Domain\ValueObject\CacheRule;
use Phil\PwaBundle\Domain\ValueObject\Icon;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Domain\ValueObject\Rule;
use Phil\PwaBundle\Domain\ValueObject\Shortcut;
use Phil\PwaBundle\Domain\ValueObject\WorkerSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Manifest::class)]
#[CoversClass(Icon::class)]
#[CoversClass(Shortcut::class)]
#[CoversClass(CacheRule::class)]
#[CoversClass(WorkerSettings::class)]
#[CoversClass(Rule::class)]
#[CoversClass(DisplayMode::class)]
#[CoversClass(InvalidManifest::class)]
final class ManifestTest extends TestCase
{
    public function testAManifestHasItsDefaults(): void
    {
        $manifest = new Manifest('  Example   site ');

        self::assertSame('Example site', $manifest->name);
        self::assertNull($manifest->shortName);
        self::assertNull($manifest->description);
        self::assertSame('/', $manifest->id);
        self::assertSame('/', $manifest->startUrl);
        self::assertSame('/', $manifest->scope);
        self::assertSame(DisplayMode::Standalone, $manifest->display);
        self::assertSame([], $manifest->displayOverride);
        self::assertNull($manifest->orientation);
        self::assertNull($manifest->themeColor);
        self::assertNull($manifest->backgroundColor);
        self::assertNull($manifest->lang);
        self::assertNull($manifest->dir);
        self::assertSame([], $manifest->icons);
        self::assertSame([], $manifest->shortcuts);
        self::assertSame([], $manifest->categories);
    }

    public function testAManifestKeepsEveryMember(): void
    {
        $icon = new Icon('images/icon.png', '192x192 512x512', 'image/png', [IconPurpose::Maskable, IconPurpose::Monochrome]);
        $shortcut = new Shortcut(' Schedule ', '/app/schedule?day=1', ' Sched ', ' The programme ');
        $manifest = new Manifest('Example', ' Ex ', ' A site ', '/app/', '/app/./start/../?source=pwa', '/app/', DisplayMode::Fullscreen, [DisplayMode::WindowControlsOverlay, DisplayMode::Tabbed], Orientation::Landscape, '#1B1b1b', 'white', 'fr-CA', 'rtl', [$icon], [$shortcut], ['events', 'social networking']);

        self::assertSame('Ex', $manifest->shortName);
        self::assertSame('A site', $manifest->description);
        self::assertSame('/app/', $manifest->id);
        self::assertSame('/app/?source=pwa', $manifest->startUrl);
        self::assertSame('/app/', $manifest->scope);
        self::assertSame(DisplayMode::Fullscreen, $manifest->display);
        self::assertSame([DisplayMode::WindowControlsOverlay, DisplayMode::Tabbed], $manifest->displayOverride);
        self::assertSame(Orientation::Landscape, $manifest->orientation);
        self::assertSame('#1B1b1b', $manifest->themeColor);
        self::assertSame('white', $manifest->backgroundColor);
        self::assertSame('fr-CA', $manifest->lang);
        self::assertSame('rtl', $manifest->dir);
        self::assertSame([$icon], $manifest->icons);
        self::assertSame([$shortcut], $manifest->shortcuts);
        self::assertSame(['events', 'social networking'], $manifest->categories);
        self::assertSame('Schedule', $shortcut->name);
        self::assertSame('/app/schedule?day=1', $shortcut->url);
        self::assertSame('Sched', $shortcut->shortName);
        self::assertSame('The programme', $shortcut->description);
        self::assertSame([IconPurpose::Maskable, IconPurpose::Monochrome], $icon->purposes);
        self::assertSame('192x192 512x512', $icon->sizes);
        self::assertSame('image/png', $icon->type);
        self::assertSame('images/icon.png', $icon->src);
    }

    public function testAnIconHasItsDefaults(): void
    {
        $icon = new Icon('/favicon.svg');

        self::assertSame('/favicon.svg', $icon->src);
        self::assertSame('any', $icon->sizes);
        self::assertNull($icon->type);
        self::assertSame([IconPurpose::Any], $icon->purposes);
        self::assertNull(new Shortcut('A', '/a')->shortName);
        self::assertNull(new Shortcut('A', '/a')->description);
    }

    public function testOnlyTheFourFallbackModesAreADisplay(): void
    {
        self::assertTrue(DisplayMode::Fullscreen->isFallback());
        self::assertTrue(DisplayMode::Standalone->isFallback());
        self::assertTrue(DisplayMode::MinimalUi->isFallback());
        self::assertTrue(DisplayMode::Browser->isFallback());
        self::assertFalse(DisplayMode::WindowControlsOverlay->isFallback());
        self::assertFalse(DisplayMode::Tabbed->isFallback());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function localPaths(): iterable
    {
        yield 'root' => ['/', '/'];
        yield 'dot segments' => ['/a/./b/../c', '/a/c'];
        yield 'encoded dot segments' => ['/a/%2e%2e/b', '/b'];
        yield 'query' => ['/?source=pwa&x=1', '/?source=pwa&x=1'];
        yield 'non-ASCII' => ['/café', '/caf%C3%A9'];
        yield 'empty query' => ['/a?', '/a?'];
    }

    #[DataProvider('localPaths')]
    public function testALocalPathIsResolvedAsABrowserDoes(string $path, string $resolved): void
    {
        self::assertSame($resolved, Rule::localPath($path, 'path'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notLocal(): iterable
    {
        yield 'relative' => ['app/'];
        yield 'empty' => [''];
        yield 'another host' => ['//evil.example/'];
        yield 'a host through a backslash' => ['/\\evil.example/'];
        yield 'a host through dot segments' => ['/.//evil.example/'];
        yield 'absolute' => ['https://evil.example/'];
        yield 'fragment' => ['/a#b'];
        yield 'space' => ['/a b'];
        yield 'tab' => ["/a\tb"];
        yield 'delete' => ["/a\x7Fb"];
        yield 'too long' => ['/'.str_repeat('a', 2048)];
    }

    #[DataProvider('notLocal')]
    public function testAPathThatIsNotLocalIsRefused(string $path): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The start address is a path of the site from its root, such as /app/, without fragment, spaces or control characters.');

        new Manifest('A', startUrl: $path);
    }

    public function testAPathOfTheLargestLengthIsKept(): void
    {
        self::assertSame(2048, \strlen(Rule::localPath('/'.str_repeat('a', 2047), 'path')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function assetPaths(): iterable
    {
        yield 'file' => ['icon.png'];
        yield 'nested' => ['images/icons/icon-192.png'];
        yield 'namespace' => ['@phil/pwa-bundle/service_worker.js'];
        yield 'dots in names' => ['a.b/c..d.png'];
    }

    #[DataProvider('assetPaths')]
    public function testAnAssetPathIsKept(string $path): void
    {
        self::assertSame($path, Rule::source($path, 'source'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badAssetPaths(): iterable
    {
        yield 'parent' => ['images/../secret.png'];
        yield 'leading parent' => ['../secret.png'];
        yield 'current' => ['./icon.png'];
        yield 'trailing current' => ['images/.'];
        yield 'double slash' => ['images//icon.png'];
        yield 'trailing slash' => ['images/'];
        yield 'scheme' => ['https://cdn.example/icon.png'];
        yield 'space' => ['my icon.png'];
        yield 'new line' => ["icon.png\n"];
        yield 'too long' => [str_repeat('a', 2049)];
    }

    #[DataProvider('badAssetPaths')]
    public function testABadAssetPathIsRefused(string $path): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains('The source of an icon is a path of the site from its root, or the logical path of an asset without "." or ".." segments.');

        new Icon($path);
    }

    public function testAnAssetPathOfTheLargestLengthIsKept(): void
    {
        self::assertSame(2048, \strlen(Rule::assetPath(str_repeat('a', 2048), 'asset')));
    }

    public function testWithinMeansUnderTheScope(): void
    {
        self::assertTrue(Rule::isWithin('/app/', '/app/'));
        self::assertTrue(Rule::isWithin('/app/x', '/app/'));
        self::assertFalse(Rule::isWithin('/apple', '/app/'));
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refused(): iterable
    {
        yield 'blank name' => [static fn (): Manifest => new Manifest(' '), 'The name is one line of 1 to 120 characters, without control characters.'];
        yield 'long name' => [static fn (): Manifest => new Manifest(str_repeat('n', 121)), 'The name is one line of 1 to 120'];
        yield 'name with control' => [static fn (): Manifest => new Manifest("A\x01"), 'The name is one line'];
        yield 'name not UTF-8' => [static fn (): Manifest => new Manifest("\xFF"), 'The name is one line'];
        yield 'long short name' => [static fn (): Manifest => new Manifest('A', str_repeat('s', 31)), 'The short name is one line of 1 to 30'];
        yield 'long description' => [static fn (): Manifest => new Manifest('A', null, str_repeat('d', 301)), 'The description is one line of 1 to 300'];
        yield 'start outside scope' => [static fn (): Manifest => new Manifest('A', startUrl: '/other/', scope: '/app/'), 'The start address is within the scope.'];
        yield 'display not a fallback' => [static fn (): Manifest => new Manifest('A', display: DisplayMode::Tabbed), 'The display is "fullscreen", "standalone", "minimal-ui" or "browser"; other modes go in the display override.'];
        yield 'override twice' => [static fn (): Manifest => new Manifest('A', displayOverride: [DisplayMode::Fullscreen, DisplayMode::Fullscreen]), 'Each mode of the display override is given once.'];
        yield 'theme colour' => [static fn (): Manifest => new Manifest('A', themeColor: 'red;'), 'The theme colour is a hexadecimal colour, such as #1b1b1b, or a CSS colour keyword.'];
        yield 'five digits' => [static fn (): Manifest => new Manifest('A', themeColor: '#12345'), 'The theme colour is a hexadecimal colour'];
        yield 'two digits' => [static fn (): Manifest => new Manifest('A', themeColor: '#12'), 'The theme colour is a hexadecimal colour'];
        yield 'nine digits' => [static fn (): Manifest => new Manifest('A', themeColor: '#123456789'), 'The theme colour is a hexadecimal colour'];
        yield 'colour after a prefix' => [static fn (): Manifest => new Manifest('A', themeColor: 'x#fff'), 'The theme colour is a hexadecimal colour'];
        yield 'colour with new line' => [static fn (): Manifest => new Manifest('A', themeColor: "red\n"), 'The theme colour is a hexadecimal colour'];
        yield 'background colour' => [static fn (): Manifest => new Manifest('A', backgroundColor: 'url(x)'), 'The background colour is a hexadecimal colour'];
        yield 'language' => [static fn (): Manifest => new Manifest('A', lang: 'french'), 'The language is a language tag, such as fr-CA.'];
        yield 'language with new line' => [static fn (): Manifest => new Manifest('A', lang: "fr\n"), 'The language is a language tag'];
        yield 'direction' => [static fn (): Manifest => new Manifest('A', dir: 'up'), 'The direction is "ltr", "rtl" or "auto".'];
        yield 'too many icons' => [static fn (): Manifest => new Manifest('A', icons: array_fill(0, 31, new Icon('/i.png'))), 'A manifest has 30 icons at most.'];
        yield 'too many shortcuts' => [static fn (): Manifest => new Manifest('A', shortcuts: array_fill(0, 11, new Shortcut('S', '/s'))), 'A manifest has 10 shortcuts at most.'];
        yield 'shortcut outside scope' => [static fn (): Manifest => new Manifest('A', startUrl: '/app/', scope: '/app/', shortcuts: [new Shortcut('S', '/elsewhere')]), 'The address of a shortcut is within the scope.'];
        yield 'too many categories' => [static fn (): Manifest => new Manifest('A', categories: array_map(static fn (int $i): string => 'c'.str_repeat('a', $i), range(1, 11))), 'A manifest has 10 categories at most, each once.'];
        yield 'category twice' => [static fn (): Manifest => new Manifest('A', categories: ['games', 'games']), 'A manifest has 10 categories at most, each once.'];
        yield 'bad category' => [static fn (): Manifest => new Manifest('A', categories: ['Games']), 'A category is 1 to 32 lower-case letters, spaces or "-", such as "books".'];
        yield 'category with new line' => [static fn (): Manifest => new Manifest('A', categories: ["games\n"]), 'A category is 1 to 32'];
        yield 'long category' => [static fn (): Manifest => new Manifest('A', categories: [str_repeat('c', 33)]), 'A category is 1 to 32'];
        yield 'icon sizes' => [static fn (): Icon => new Icon('/i.png', '192'), 'The sizes of an icon are "any", or sizes such as "192x192 512x512".'];
        yield 'icon size zero' => [static fn (): Icon => new Icon('/i.png', '0x0'), 'The sizes of an icon are'];
        yield 'icon sizes with new line' => [static fn (): Icon => new Icon('/i.png', "any\n"), 'The sizes of an icon are'];
        yield 'icon size too large' => [static fn (): Icon => new Icon('/i.png', '10000x10000'), 'The sizes of an icon are'];
        yield 'eleven icon sizes' => [static fn (): Icon => new Icon('/i.png', implode(' ', array_fill(0, 11, '16x16'))), 'The sizes of an icon are'];
        yield 'icon type' => [static fn (): Icon => new Icon('/i.png', 'any', 'text/html'), 'The type of an icon is the media type of an image, such as image/png.'];
        yield 'icon type after a prefix' => [static fn (): Icon => new Icon('/i.png', 'any', 'x-image/png'), 'The type of an icon is'];
        yield 'icon type with new line' => [static fn (): Icon => new Icon('/i.png', 'any', "image/png\n"), 'The type of an icon is'];
        yield 'icon purpose twice' => [static fn (): Icon => new Icon('/i.png', purposes: [IconPurpose::Any, IconPurpose::Any]), 'Each purpose of an icon is given once.'];
        yield 'shortcut name' => [static fn (): Shortcut => new Shortcut(str_repeat('s', 61), '/s'), 'The name of a shortcut is one line of 1 to 60'];
        yield 'shortcut short name' => [static fn (): Shortcut => new Shortcut('S', '/s', str_repeat('s', 31)), 'The short name of a shortcut is one line of 1 to 30'];
        yield 'shortcut description' => [static fn (): Shortcut => new Shortcut('S', '/s', null, str_repeat('s', 301)), 'The description of a shortcut is one line of 1 to 300'];
        yield 'shortcut address' => [static fn (): Shortcut => new Shortcut('S', 'https://evil.example/'), 'The address of a shortcut is a path of the site'];
        yield 'rule prefix' => [static fn (): CacheRule => new CacheRule('assets/', CacheStrategy::CacheFirst), 'The prefix of a cache rule is a path of the site'];
        yield 'cache name' => [static fn (): WorkerSettings => new WorkerSettings('Phil PWA'), 'The cache of the service worker is 1 to 32 lower-case letters, digits or "-".'];
        yield 'cache name with new line' => [static fn (): WorkerSettings => new WorkerSettings("pwa\n"), 'The cache of the service worker is'];
        yield 'long cache name' => [static fn (): WorkerSettings => new WorkerSettings(str_repeat('c', 33)), 'The cache of the service worker is'];
        yield 'worker scope not a directory' => [static fn (): WorkerSettings => new WorkerSettings('pwa', '/app'), 'The scope of the service worker is a directory: it ends with "/".'];
        yield 'worker scope not local' => [static fn (): WorkerSettings => new WorkerSettings('pwa', 'app/'), 'The scope of the service worker is a path of the site'];
        yield 'too many rules' => [static fn (): WorkerSettings => new WorkerSettings(rules: array_fill(0, 21, new CacheRule('/a/', CacheStrategy::CacheFirst))), 'The service worker has 20 cache rules and 20 exclusions at most.'];
        yield 'too many exclusions' => [static fn (): WorkerSettings => new WorkerSettings(exclude: array_fill(0, 21, '/a/')), 'The service worker has 20 cache rules and 20 exclusions at most.'];
        yield 'excluded prefix' => [static fn (): WorkerSettings => new WorkerSettings(exclude: ['admin']), 'The excluded prefix is a path of the site'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refused')]
    public function testItRefuses(callable $build, string $message): void
    {
        $this->expectException(InvalidManifest::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheLargestMembersAreKept(): void
    {
        $manifest = new Manifest(
            str_repeat('n', 120),
            str_repeat('s', 30),
            str_repeat('d', 300),
            themeColor: '#12345678',
            backgroundColor: '#1234',
            lang: 'zh-Hant-TW-x-abcdefgh',
            icons: array_fill(0, 30, new Icon('/i.png', implode(' ', array_fill(0, 10, '9999x9999')), 'image/svg+xml')),
            shortcuts: array_fill(0, 10, new Shortcut(str_repeat('s', 60), '/s', str_repeat('s', 30), str_repeat('d', 300))),
            categories: array_map(static fn (int $i): string => 'c'.str_repeat('a', $i), range(1, 10)),
        );

        self::assertCount(30, $manifest->icons);
        self::assertCount(10, $manifest->shortcuts);
        self::assertCount(10, $manifest->categories);
        self::assertSame('#fff', new Manifest('A', themeColor: '#fff')->themeColor);
        self::assertSame('#123456', new Manifest('A', themeColor: '#123456')->themeColor);
        self::assertSame(str_repeat('c', 32), new Manifest('A', categories: [str_repeat('c', 32)])->categories[0]);
        self::assertSame('ltr', new Manifest('A', dir: 'ltr')->dir);
        self::assertSame('auto', new Manifest('A', dir: 'auto')->dir);
    }

    public function testWorkerSettingsHaveTheirDefaultsAndKeepTheirMembers(): void
    {
        $defaults = new WorkerSettings();
        self::assertSame('phil-pwa', $defaults->cache);
        self::assertSame('/', $defaults->scope);
        self::assertSame([], $defaults->rules);
        self::assertSame([], $defaults->exclude);
        self::assertTrue($defaults->offline);

        $rule = new CacheRule('/assets/./x/../', CacheStrategy::NetworkFirst);
        self::assertSame('/assets/', $rule->prefix);
        self::assertSame(CacheStrategy::NetworkFirst, $rule->strategy);

        $settings = new WorkerSettings(str_repeat('c', 32), '/app/', array_fill(0, 20, $rule), array_fill(0, 20, '/app/admin/./'), false);
        self::assertSame(str_repeat('c', 32), $settings->cache);
        self::assertSame('/app/', $settings->scope);
        self::assertCount(20, $settings->rules);
        self::assertSame('/app/admin/', $settings->exclude[0]);
        self::assertFalse($settings->offline);
    }

    public function testTheLengthsAreCountedInCharacters(): void
    {
        self::assertSame(str_repeat('é', 30), new Manifest('A', shortName: str_repeat('é', 30))->shortName);
    }
}
