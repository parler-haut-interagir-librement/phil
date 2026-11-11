<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle;

use Override;
use Phil\ReferencesBundle\Application\ReferenceCollector;
use Phil\ReferencesBundle\Application\References;
use Phil\ReferencesBundle\Application\ReferenceTarget;
use Phil\ReferencesBundle\Infrastructure\Console\CheckReferencesCommand;
use Phil\ReferencesBundle\Infrastructure\Doctrine\DbalUsageRepository;
use Phil\ReferencesBundle\Infrastructure\Doctrine\ReferenceTrackingListener;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageSchemaListener;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Where resources are used: the links collected from each source, kept in a table of a Doctrine connection and up to
 * date flush by flush; the deletion of a resource still used refused; the links to resources that are gone listed.
 */
final class PhilReferencesBundle extends AbstractBundle
{
    public const string COLLECTOR_TAG = 'phil_references.collector';

    public const string TARGET_TAG = 'phil_references.target';

    #[Override]
    protected string $extensionAlias = 'phil_references';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')
                    ->info('The Doctrine connection that keeps the usages, and whose entities are tracked.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('track_entities')
                    ->info('Whether the usages of Doctrine entities are kept up to date at each flush.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('block_deletion')
                    ->info('Whether deleting an entity still used aborts the flush.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{connection: string, track_entities: bool, block_deletion: bool} $config */
        $builder->registerForAutoconfiguration(ReferenceCollector::class)->addTag(self::COLLECTOR_TAG);
        $builder->registerForAutoconfiguration(ReferenceTarget::class)->addTag(self::TARGET_TAG);
        $services = $container->services();
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        // The port by reflection: the bundle class wires the adapter, and names no class of the domain.
        $services->set(DbalUsageRepository::class)->args([$connection]);
        $port = new ReflectionClass(DbalUsageRepository::class)->getInterfaceNames()[0];
        $services->alias($port, DbalUsageRepository::class)->public();

        $services->set(References::class)
            ->args([new Reference($port), new TaggedIteratorArgument(self::COLLECTOR_TAG), new TaggedIteratorArgument(self::TARGET_TAG)])
            ->public();
        $services->set(UsageSchemaListener::class)
            ->args([$connection])
            ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);

        if ($config['track_entities']) {
            $services->set(ReferenceTrackingListener::class)
                ->args([new Reference(References::class), $config['block_deletion']])
                ->tag('doctrine.event_listener', ['event' => 'onFlush', 'connection' => $config['connection']])
                ->tag('doctrine.event_listener', ['event' => 'postFlush', 'connection' => $config['connection']])
                ->tag('doctrine.event_listener', ['event' => 'onClear', 'connection' => $config['connection']])
                ->tag('kernel.reset', ['method' => 'reset']);
        }

        if (class_exists(Command::class)) {
            $services->set(CheckReferencesCommand::class)->args([new Reference(References::class)])->tag('console.command');
        }
    }
}
