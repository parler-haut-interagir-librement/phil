<?php

declare(strict_types=1);

namespace Phil\DddBundle;

use Override;
use Phil\DddBundle\Application\Bus\CommandBus;
use Phil\DddBundle\Application\Bus\CommandHandler;
use Phil\DddBundle\Application\Bus\QueryBus;
use Phil\DddBundle\Application\Bus\QueryHandler;
use Phil\DddBundle\Infrastructure\Messenger\MessengerCommandBus;
use Phil\DddBundle\Infrastructure\Messenger\MessengerQueryBus;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The DDD/CQRS core: two bus ports, their Messenger adapters, and the markers that decide the bus.
 *
 * **The bundle does not declare the Messenger buses themselves.** Their middleware — `doctrine_transaction`
 * on commands, no transaction on queries — is a decision of the application, and it reads in the
 * application's `messenger.yaml`. The bundle only plugs into it through the configured service ids.
 */
final class PhilDddBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_ddd';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('command_bus')
                    ->info('Service id of the Messenger bus that carries commands; it must be transactional.')
                    ->defaultValue('command.bus')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('query_bus')
                    ->info('Service id of the Messenger bus that carries queries; it must not be.')
                    ->defaultValue('query.bus')
                    ->cannotBeEmpty()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $commandBus = $this->busId($config, 'command_bus');
        $queryBus = $this->busId($config, 'query_bus');

        $services = $container->services();

        $services->set(MessengerCommandBus::class)
            ->args([service($commandBus)]);
        $services->alias(CommandBus::class, MessengerCommandBus::class);

        $services->set(MessengerQueryBus::class)
            ->args([service($queryBus)]);
        $services->alias(QueryBus::class, MessengerQueryBus::class);

        // The markers decide the bus. Without this, an untagged handler lands on the default bus, and a
        // command routed to the query bus loses its transaction — silently.
        $builder->registerForAutoconfiguration(CommandHandler::class)
            ->addTag('messenger.message_handler', ['bus' => $commandBus]);
        $builder->registerForAutoconfiguration(QueryHandler::class)
            ->addTag('messenger.message_handler', ['bus' => $queryBus]);
    }

    /**
     * A scalar node also accepts an integer or a boolean; a service id is only ever a string.
     *
     * @param array<array-key, mixed> $config
     */
    private function busId(array $config, string $key): string
    {
        $id = $config[$key] ?? null;

        if (!\is_string($id)) {
            throw new InvalidConfigurationException(\sprintf('"phil_ddd.%s" must be a service id.', $key));
        }

        return $id;
    }
}
