<?php

declare(strict_types=1);

namespace Phil\UxKit;

use Override;
use Phil\UxKit\Application\ImageOrigin;
use Phil\UxKit\Infrastructure\Twig\ImageExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;

/**
 * The PHP the recipes of the kit call from Twig: `image_origin()`, which keeps the images of other hosts off the page
 * unless their host is listed, and `image_sources()`, which reads the files of an image among the assets.
 */
final class PhilUxKitBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_ux_kit';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('image')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('hosts')
                            ->info('The hosts whose images a page may show, those under each included; every other host is refused.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                        ->end()
                        ->booleanNode('allow_localhost')
                            ->info('Whether localhost and the names under .localhost are hosts: true in development only.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $image = \is_array($config['image'] ?? null) ? $config['image'] : [];
        $hosts = \is_array($image['hosts'] ?? null) ? array_values(array_filter($image['hosts'], is_string(...))) : [];

        $services = $container->services();
        $services->set(ImageOrigin::class)
            ->args([$hosts, true === ($image['allow_localhost'] ?? false)]);
        $services->set(ImageExtension::class)
            ->args([new ReferenceConfigurator(ImageOrigin::class), new ReferenceConfigurator('request_stack')->nullOnInvalid(), new ReferenceConfigurator('asset_mapper')->nullOnInvalid()])
            ->tag('twig.attribute_extension')
            ->tag('twig.runtime');
    }
}
