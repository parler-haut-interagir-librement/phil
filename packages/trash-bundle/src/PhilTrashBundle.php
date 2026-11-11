<?php

declare(strict_types=1);

namespace Phil\TrashBundle;

use Override;
use Phil\TrashBundle\Application\Trash;
use Phil\TrashBundle\Application\TrashHandler;
use Phil\TrashBundle\Infrastructure\Console\PurgeTrashCommand;
use Phil\TrashBundle\Infrastructure\Doctrine\DoctrineTrashItemRepository;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A trash for an application's resources: each kind goes in and comes back by a `TrashHandler` the application
 * writes, and is purged once its retention is over. The trash itself is a Doctrine table.
 */
final class PhilTrashBundle extends AbstractBundle
{
    public const string HANDLER_TAG = 'phil_trash.handler';

    #[Override]
    protected string $extensionAlias = 'phil_trash';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('retention')
                    ->info('How many days a trashed resource is kept before it is purged.')
                    ->min(1)
                    ->defaultValue(30)
                ->end()
                ->arrayNode('retention_by_kind')
                    ->info('Days kept for some kinds of resources: { page: 90 }.')
                    ->useAttributeAsKey('kind')
                    ->integerPrototype()->min(1)->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilTrashBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\TrashBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(TrashHandler::class)->addTag(self::HANDLER_TAG);
        $services = $container->services();

        // The port by reflection: the bundle class wires the adapter, and names no class of the domain.
        $services->set(DoctrineTrashItemRepository::class)->args([new Reference('doctrine.orm.entity_manager')]);

        foreach (new ReflectionClass(DoctrineTrashItemRepository::class)->getInterfaceNames() as $port) {
            $services->alias($port, DoctrineTrashItemRepository::class)->public();
            $repository = $port;
        }

        $services->set(Trash::class)
            ->args([
                new Reference($repository ?? DoctrineTrashItemRepository::class),
                new TaggedIteratorArgument(self::HANDLER_TAG),
                new Reference('clock'),
                $config['retention'],
                $config['retention_by_kind'],
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->public();

        if (class_exists(Command::class)) {
            $services->set(PurgeTrashCommand::class)->args([new Reference(Trash::class)])->tag('console.command');
        }
    }
}
