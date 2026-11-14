<?php

declare(strict_types=1);

namespace Phil\StockroomBundle;

use Override;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;
use Phil\StockroomBundle\Infrastructure\DependencyInjection\StockroomServices;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * `phil/stockroom` in Symfony: its five stores in six tables of a Doctrine DBAL connection, or in memory, and the
 * services on them.
 *
 * ```yaml
 * phil_stockroom:
 *     store: dbal
 *     connection: default
 *     table_prefix: phil_stockroom_
 * ```
 */
final class PhilStockroomBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_stockroom';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the stockroom lives: "dbal", six tables of a DBAL connection; "memory", one process, for tests.')
                    ->values([StockroomServices::STORE_DBAL, StockroomServices::STORE_MEMORY])
                    ->defaultValue(StockroomServices::STORE_DBAL)
                ->end()
                ->scalarNode('connection')
                    ->info('The name of the DBAL connection of DoctrineBundle the tables are in.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $name): bool => !\is_string($name) || 1 !== preg_match('/^\w{1,64}$/D', $name))
                        ->thenInvalid('The connection is named by 1 to 64 letters, digits or "_", not %s.')
                    ->end()
                ->end()
                ->scalarNode('table_prefix')
                    ->info('The prefix of the six table names: 1 to 40 lowercase letters, digits or "_", not starting with a digit.')
                    ->defaultValue(StockroomTables::DEFAULT_PREFIX)
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $prefix): bool => !\is_string($prefix) || 1 !== preg_match(StockroomTables::PREFIX_PATTERN, $prefix))
                        ->thenInvalid('The table prefix is 1 to 40 lowercase letters, digits or "_", not starting with a digit, not %s.')
                    ->end()
                ->end()
                ->scalarNode('clock')
                    ->info('The service id of the PSR-20 clock the movements are dated by.')
                    ->defaultValue('clock')
                    ->cannotBeEmpty()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, connection: string, table_prefix: string, clock: string} $config */
        StockroomServices::register($builder, $config);
    }
}
