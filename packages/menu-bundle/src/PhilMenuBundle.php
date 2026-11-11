<?php

declare(strict_types=1);

namespace Phil\MenuBundle;

use Override;
use Phil\MenuBundle\Application\MenuBuilder;
use Phil\MenuBundle\Application\MenuDecorator;
use Phil\MenuBundle\Application\Menus;
use Phil\MenuBundle\Infrastructure\Twig\MenuExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFunction;

/**
 * Menus: the services implementing `MenuBuilder` and `MenuDecorator` are found by autoconfiguration, `Menus` puts
 * them together, and Twig shows the menus.
 */
final class PhilMenuBundle extends AbstractBundle
{
    public const string BUILDER_TAG = 'phil_menu.builder';

    public const string DECORATOR_TAG = 'phil_menu.decorator';

    #[Override]
    protected string $extensionAlias = 'phil_menu';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('allow_localhost')
                    ->info('Whether menu addresses may be on localhost or under .localhost: true in development and tests only.')
                    ->defaultFalse()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The builders of the application pass it to MenuItem::withUrl(), so that a menu links to localhost in development.
        $container->parameters()->set('phil_menu.allow_localhost', true === $config['allow_localhost']);
        $builder->registerForAutoconfiguration(MenuBuilder::class)->addTag(self::BUILDER_TAG);
        $builder->registerForAutoconfiguration(MenuDecorator::class)->addTag(self::DECORATOR_TAG);

        $services = $container->services();
        $services->set(Menus::class)
            ->args([new TaggedIteratorArgument(self::BUILDER_TAG), new TaggedIteratorArgument(self::DECORATOR_TAG)])
            ->public();

        if (class_exists(AsTwigFunction::class)) {
            $services->set(MenuExtension::class)
                ->args([
                    new Reference(Menus::class),
                    new Reference('request_stack'),
                    new Reference('translator', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                    new Reference('router', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                ])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
