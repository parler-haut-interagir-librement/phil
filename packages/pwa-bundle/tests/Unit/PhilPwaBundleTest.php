<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Tests\Unit;

use Phil\PwaBundle\Application\Contract\AssetUrls;
use Phil\PwaBundle\Application\Contract\PrecacheProvider;
use Phil\PwaBundle\Application\ManifestDocument;
use Phil\PwaBundle\Application\ManifestReader;
use Phil\PwaBundle\Application\ServiceWorkerScript;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Domain\ValueObject\WorkerSettings;
use Phil\PwaBundle\Infrastructure\Asset\PackagesAssetUrls;
use Phil\PwaBundle\Infrastructure\Asset\PlainAssetUrls;
use Phil\PwaBundle\Infrastructure\ConfiguredPwa;
use Phil\PwaBundle\Infrastructure\DependencyInjection\AssetUrlsPass;
use Phil\PwaBundle\Infrastructure\DependencyInjection\PwaServices;
use Phil\PwaBundle\Infrastructure\Http\PwaController;
use Phil\PwaBundle\Infrastructure\Twig\PwaExtension;
use Phil\PwaBundle\PhilPwaBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilPwaBundle::class)]
#[CoversClass(PwaServices::class)]
#[CoversClass(AssetUrlsPass::class)]
final class PhilPwaBundleTest extends TestCase
{
    public function testANameIsEnoughForTheDefaults(): void
    {
        $container = $this->load([['manifest' => ['name' => 'Example']]]);

        self::assertSame('phil_pwa', $this->extension()->getAlias());
        $configured = $container->getDefinition(ConfiguredPwa::class);
        self::assertSame(['cache' => 'phil-pwa', 'scope' => '/', 'cache_rules' => [['prefix' => '/assets/', 'strategy' => 'cache-first']], 'exclude' => ['/_profiler/', '/_wdt/'], 'offline' => true], $configured->getArgument(1));
        $manifest = $configured->getArgument(0);
        self::assertIsArray($manifest);
        self::assertSame('Example', $manifest['name']);
        self::assertSame('standalone', $manifest['display']);
        self::assertSame('/', $manifest['start_url']);

        self::assertEquals([new Reference(ConfiguredPwa::class), 'manifest'], $container->getDefinition(Manifest::class)->getFactory());
        self::assertTrue($container->getDefinition(Manifest::class)->isPublic());
        self::assertEquals([new Reference(ConfiguredPwa::class), 'worker'], $container->getDefinition(WorkerSettings::class)->getFactory());
        self::assertSame(PackagesAssetUrls::class, (string) $container->getAlias(AssetUrls::class));
        self::assertTrue($container->getAlias(AssetUrls::class)->isPublic());
        self::assertEquals([new Reference('assets.packages')], $container->getDefinition(PackagesAssetUrls::class)->getArguments());
        self::assertTrue($container->hasDefinition(PlainAssetUrls::class));
        self::assertTrue($container->getDefinition(ManifestDocument::class)->isPublic());
        self::assertEquals([new Reference(AssetUrls::class)], $container->getDefinition(ManifestDocument::class)->getArguments());
        self::assertTrue($container->hasDefinition(ManifestReader::class));

        $script = $container->getDefinition(ServiceWorkerScript::class);
        self::assertTrue($script->isPublic());
        self::assertSame('@phil/pwa-bundle/service_worker.js', $script->getArgument(2));
        self::assertSame([], $script->getArgument(3));
        self::assertEquals(new TaggedIteratorArgument(PwaServices::PRECACHE_TAG), $script->getArgument(4));

        $controller = $container->getDefinition(PwaController::class);
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertSame('/', $controller->getArgument(3));
        self::assertEquals(new Reference('router'), $controller->getArgument(5));
        self::assertSame(PhilPwaBundle::LAYOUT, $controller->getArgument(6));
        self::assertSame(600, $controller->getArgument(7));

        $extension = $container->getDefinition(PwaExtension::class);
        self::assertSame('/', $extension->getArgument(2));
        self::assertTrue($extension->hasTag('twig.attribute_extension'));
        self::assertTrue($extension->hasTag('twig.runtime'));

        self::assertArrayHasKey(PrecacheProvider::class, $container->getAutoconfiguredInstanceof());
        self::assertTrue($container->getAutoconfiguredInstanceof()[PrecacheProvider::class]->hasTag(PwaServices::PRECACHE_TAG));
    }

    public function testEverySettingIsPassedOn(): void
    {
        $container = $this->load([[
            'layout' => 'base.html.twig',
            'cache_max_age' => 0,
            'manifest' => [
                'name' => 'Example',
                'short_name' => 'Ex',
                'start_url' => '/app/',
                'scope' => '/app/',
                'display' => 'fullscreen',
                'display_override' => ['window-controls-overlay'],
                'orientation' => 'landscape',
                'theme_color' => '#000',
                'dir' => 'rtl',
                'icons' => [['src' => 'icon.png', 'purpose' => ['maskable']]],
                'shortcuts' => [['name' => 'S', 'url' => '/app/s']],
                'categories' => ['events'],
            ],
            'service_worker' => [
                'cache' => 'site',
                'scope' => '/app/',
                'script' => 'worker.js',
                'precache' => ['app.css', '/app/'],
                'offline' => false,
                'cache_rules' => [['prefix' => '/app/api/', 'strategy' => 'network-first']],
                'exclude' => [],
            ],
        ]]);

        $manifest = $container->getDefinition(ConfiguredPwa::class)->getArgument(0);
        self::assertIsArray($manifest);
        self::assertIsArray($manifest['icons']);
        self::assertSame(['src' => 'icon.png', 'purpose' => ['maskable'], 'sizes' => 'any', 'type' => null], $manifest['icons'][0]);
        self::assertSame([['name' => 'S', 'url' => '/app/s', 'short_name' => null, 'description' => null]], $manifest['shortcuts']);
        self::assertSame(['cache' => 'site', 'scope' => '/app/', 'cache_rules' => [['prefix' => '/app/api/', 'strategy' => 'network-first']], 'exclude' => [], 'offline' => false], $container->getDefinition(ConfiguredPwa::class)->getArgument(1));
        self::assertSame('worker.js', $container->getDefinition(ServiceWorkerScript::class)->getArgument(2));
        self::assertSame(['app.css', '/app/'], $container->getDefinition(ServiceWorkerScript::class)->getArgument(3));
        self::assertSame('/app/', $container->getDefinition(PwaController::class)->getArgument(3));
        self::assertSame('base.html.twig', $container->getDefinition(PwaController::class)->getArgument(6));
        self::assertSame(0, $container->getDefinition(PwaController::class)->getArgument(7));
        self::assertSame('/app/', $container->getDefinition(PwaExtension::class)->getArgument(2));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'no manifest' => [[], 'The child config "manifest" under "phil_pwa" must be configured'];
        yield 'no name' => [['manifest' => []], 'The child config "name" under "phil_pwa.manifest" must be configured'];
        yield 'start outside scope' => [['manifest' => ['name' => 'A', 'start_url' => '/x/', 'scope' => '/app/']], 'phil_pwa: The start address is within the scope.'];
        yield 'bad cache' => [['manifest' => ['name' => 'A'], 'service_worker' => ['cache' => 'Bad Cache']], 'phil_pwa: The cache of the service worker is 1 to 32'];
        yield 'bad precache' => [['manifest' => ['name' => 'A'], 'service_worker' => ['precache' => ['../secret']]], 'phil_pwa.service_worker.precache: The precached path is a path of the site'];
        yield 'bad script' => [['manifest' => ['name' => 'A'], 'service_worker' => ['script' => 'https://cdn.example/sw.js']], 'phil_pwa.service_worker.script: The script of the service worker is'];
        yield 'negative max age' => [['cache_max_age' => -1, 'manifest' => ['name' => 'A']], 'The value -1 is too small for path "phil_pwa.cache_max_age"'];
        yield 'tabbed display' => [['manifest' => ['name' => 'A', 'display' => 'tabbed']], 'The value "tabbed" is not allowed for path "phil_pwa.manifest.display"'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testTheConfigurationRefuses(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load([$config]);
    }

    public function testTheAssetsAreServedByAssetMapperWhenItIsThere(): void
    {
        $bundle = new PhilPwaBundle();
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'asset_mapper';
            }
        });
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['paths' => [PhilPwaBundle::ASSETS_DIR => '@phil/pwa-bundle']]], $container->getExtensionConfig('asset_mapper'));
        self::assertFileExists(PhilPwaBundle::ASSETS_DIR.'/service_worker.js');

        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertSame([], $without->getExtensionConfig('asset_mapper'));
    }

    public function testThePassFallsBackOnLogicalPathsWithoutTheAssetComponent(): void
    {
        $container = $this->load([['manifest' => ['name' => 'Example']]]);
        new AssetUrlsPass()->process($container);
        self::assertSame(PlainAssetUrls::class, (string) $container->getAlias(AssetUrls::class));
        self::assertTrue($container->getAlias(AssetUrls::class)->isPublic());

        $withPackages = $this->load([['manifest' => ['name' => 'Example']]]);
        $withPackages->register('assets.packages', stdClass::class);
        new AssetUrlsPass()->process($withPackages);
        self::assertSame(PackagesAssetUrls::class, (string) $withPackages->getAlias(AssetUrls::class));

        $replaced = $this->load([['manifest' => ['name' => 'Example']]]);
        $replaced->setAlias(AssetUrls::class, 'app.assets');
        new AssetUrlsPass()->process($replaced);
        self::assertSame('app.assets', (string) $replaced->getAlias(AssetUrls::class));

        $empty = new ContainerBuilder();
        new AssetUrlsPass()->process($empty);
        self::assertFalse($empty->hasAlias(AssetUrls::class));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilPwaBundle()->build($container);

        self::assertContains(AssetUrlsPass::class, array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses()));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilPwaBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    public function testTheNullChoicesAndEveryPurposeAreAccepted(): void
    {
        $container = $this->load([['manifest' => ['name' => 'Example', 'orientation' => null, 'dir' => null, 'icons' => [['src' => 'icon.png', 'purpose' => ['any', 'maskable', 'monochrome']]]]]]);

        $manifest = $container->getDefinition(ConfiguredPwa::class)->getArgument(0);
        self::assertIsArray($manifest);
        self::assertNull($manifest['orientation']);
        self::assertNull($manifest['dir']);
        self::assertIsArray($manifest['icons']);
        self::assertIsArray($manifest['icons'][0]);
        self::assertSame(['any', 'maskable', 'monochrome'], $manifest['icons'][0]['purpose']);
    }
}
