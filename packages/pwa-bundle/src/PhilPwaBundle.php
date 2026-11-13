<?php

declare(strict_types=1);

namespace Phil\PwaBundle;

use Override;
use Phil\PwaBundle\Domain\Manifest\CacheStrategy;
use Phil\PwaBundle\Domain\Manifest\DisplayMode;
use Phil\PwaBundle\Domain\Manifest\Orientation;
use Phil\PwaBundle\Infrastructure\DependencyInjection\AssetUrlsPass;
use Phil\PwaBundle\Infrastructure\DependencyInjection\PwaServices;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * An installable site that reads offline, wired from its configuration:
 *
 * ```yaml
 * phil_pwa:
 *     manifest:
 *         name: Example
 *         display: fullscreen
 *         theme_color: '#1b1b1b'
 *         icons:
 *             - { src: images/icon-512.png, sizes: 512x512, type: image/png }
 *     service_worker:
 *         precache: [styles/app.css, /]
 * ```
 *
 * The manifest is served at `/manifest.webmanifest`, the service worker at `/sw.js` — its settings, then its code, an
 * asset served by AssetMapper —, and the offline page at `/offline`. A Stimulus controller registers the worker, offers
 * to install the site and to update it; another opens a page on a second screen. No Workbox, no build step.
 *
 * @phpstan-import-type Config from PwaServices
 */
final class PhilPwaBundle extends AbstractBundle
{
    /** The directory of the service worker and of the Stimulus controllers, served by AssetMapper. */
    public const string ASSETS_DIR = __DIR__.'/../assets/src';

    public const string ASSETS_NAMESPACE = '@phil/pwa-bundle';

    public const string LAYOUT = '@PhilPwa/layout.html.twig';

    #[Override]
    protected string $extensionAlias = 'phil_pwa';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AssetUrlsPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->append($this->manifest())
                ->append($this->serviceWorker())
                ->scalarNode('layout')->info('The Twig layout the offline page extends; it defines the "title" and "content" blocks.')->defaultValue(self::LAYOUT)->cannotBeEmpty()->end()
                ->integerNode('cache_max_age')->info('The seconds browsers and shared caches may keep the manifest and the offline page; 0 makes them ask each time.')->min(0)->defaultValue(600)->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::ASSETS_DIR => self::ASSETS_NAMESPACE]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        PwaServices::register($builder, $config);
    }

    private function manifest(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('manifest');
        $node
            ->info('The members of the web application manifest, in snake case.')
            ->isRequired()
            ->children()
                ->scalarNode('name')->info('The name of the installed site.')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('short_name')->info('The name under its icon, 30 characters at most.')->defaultNull()->end()
                ->scalarNode('description')->defaultNull()->end()
                ->scalarNode('id')->info('The identity of the installed site, a path from the root.')->defaultValue('/')->end()
                ->scalarNode('start_url')->info('The path it opens at, within the scope.')->defaultValue('/')->end()
                ->scalarNode('scope')->info('The paths that belong to it.')->defaultValue('/')->end()
                ->enumNode('display')->values(array_map(static fn (DisplayMode $mode): string => $mode->value, array_filter(DisplayMode::cases(), static fn (DisplayMode $mode): bool => $mode->isFallback())))->defaultValue(DisplayMode::Standalone->value)->end()
                ->arrayNode('display_override')->info('The modes asked for first, in order.')->enumPrototype()->values(array_map(static fn (DisplayMode $mode): string => $mode->value, DisplayMode::cases()))->end()->end()
                ->enumNode('orientation')->values([null, ...array_map(static fn (Orientation $orientation): string => $orientation->value, Orientation::cases())])->defaultNull()->end()
                ->scalarNode('theme_color')->defaultNull()->end()
                ->scalarNode('background_color')->defaultNull()->end()
                ->scalarNode('lang')->defaultNull()->end()
                ->enumNode('dir')->values([null, 'ltr', 'rtl', 'auto'])->defaultNull()->end()
                ->arrayNode('categories')->scalarPrototype()->end()->end()
                ->arrayNode('icons')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('src')->info('A path of the site, or the logical path of an asset.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('sizes')->defaultValue('any')->end()
                            ->scalarNode('type')->defaultNull()->end()
                            ->arrayNode('purpose')->enumPrototype()->values(['any', 'maskable', 'monochrome'])->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('shortcuts')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('url')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('short_name')->defaultNull()->end()
                            ->scalarNode('description')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function serviceWorker(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('service_worker');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('cache')->info('The prefix of the names of its caches.')->defaultValue('phil-pwa')->end()
                ->scalarNode('scope')->info('The directory it controls, given to the Stimulus controller.')->defaultValue('/')->end()
                ->scalarNode('script')->info('The logical path of the code of the worker, served by AssetMapper.')->defaultValue(self::ASSETS_NAMESPACE.'/service_worker.js')->cannotBeEmpty()->end()
                ->arrayNode('precache')->info('Paths of the site, or logical paths of assets, cached when the worker installs.')->scalarPrototype()->end()->end()
                ->booleanNode('offline')->info('Cache the offline page and show it when a page cannot be fetched.')->defaultTrue()->end()
                ->arrayNode('cache_rules')
                    ->info('How requests are answered, by the prefix of their path; the first that matches decides; others go to the network.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('prefix')->isRequired()->cannotBeEmpty()->end()
                            ->enumNode('strategy')->values(array_map(static fn (CacheStrategy $strategy): string => $strategy->value, CacheStrategy::cases()))->isRequired()->end()
                        ->end()
                    ->end()
                    ->defaultValue([['prefix' => '/assets/', 'strategy' => CacheStrategy::CacheFirst->value]])
                ->end()
                ->arrayNode('exclude')->info('Prefixes of the paths the worker never answers.')->scalarPrototype()->end()->defaultValue(['/_profiler/', '/_wdt/'])->end()
            ->end();

        return $node;
    }
}
