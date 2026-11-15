<?php

declare(strict_types=1);

namespace Phil\VideoBundle;

use Override;
use Phil\VideoBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\VideoBundle\Infrastructure\DependencyInjection\VideoServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Videos in Symfony: hosted ones, files or an HLS stream from a connected service, played by the browser's video
 * element through the kit's recipe Video; linked ones, of YouTube or Vimeo, behind a facade that loads nothing of the
 * platform before the viewer asks; WebVTT captions and chapters, and the transcript beside the video, from
 * phil/timed-text; and the share watched and an aggregate heat map, counted only with the viewer's consent and said by
 * an event.
 *
 * ```yaml
 * phil_video:
 *     tracking:
 *         enabled: true
 *         consent: app.video_consent
 *         store: cache
 * ```
 */
final class PhilVideoBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_video';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('tracking')
                    ->info('The share watched and the heat map of hosted videos, counted only for viewers who agreed.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Whether viewings are counted at all; off by default.')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('endpoint')
                            ->info('The path the player sends viewings to: the route of "@PhilVideoBundle/config/routes.php".')
                            ->cannotBeEmpty()
                            ->defaultValue('/_phil/video/progress')
                        ->end()
                        ->scalarNode('consent')
                            ->info('The service, a ViewingConsent, that says whether the current viewer agreed; nobody agreed when null.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('store')
                            ->info('Where the heat maps are kept: "memory" for tests, "cache" in the cache pool below, or the service of a ViewingStore.')
                            ->cannotBeEmpty()
                            ->defaultValue('memory')
                        ->end()
                        ->scalarNode('cache_pool')
                            ->info('The PSR-6 cache pool of the "cache" store.')
                            ->cannotBeEmpty()
                            ->defaultValue('cache.app')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{tracking: array{enabled: bool, endpoint: string, consent: ?string, store: string, cache_pool: string}, kit_subdirectory: ?string} $config */
        VideoServices::register($builder, $config);
    }
}
