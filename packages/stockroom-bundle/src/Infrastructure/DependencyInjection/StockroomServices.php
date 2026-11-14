<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Stockroom\Contract\Balances;
use Phil\Stockroom\Contract\Containers;
use Phil\Stockroom\Contract\Items;
use Phil\Stockroom\Contract\Journal;
use Phil\Stockroom\Contract\LoadLists;
use Phil\Stockroom\Contract\StockReader;
use Phil\Stockroom\Deployments;
use Phil\Stockroom\LoadListReader;
use Phil\Stockroom\Memory\InMemoryBalances;
use Phil\Stockroom\Memory\InMemoryContainers;
use Phil\Stockroom\Memory\InMemoryItems;
use Phil\Stockroom\Memory\InMemoryJournal;
use Phil\Stockroom\Memory\InMemoryLoadLists;
use Phil\Stockroom\Stockroom;
use Phil\StockroomBundle\Infrastructure\Console\SchemaCommand;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalBalances;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalContainers;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalItems;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalLoadLists;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomSchemaListener;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of the configuration: the five stores — in tables of a DBAL connection, or in memory —, `Stockroom`,
 * `Deployments` and `LoadListReader` on them, `StockroomTransactions`, the schema listener when the ORM is installed and
 * the schema command when Console is.
 *
 * @phpstan-type Config array{store: string, connection: string, table_prefix: string, clock: string}
 */
final class StockroomServices
{
    /** The `store` that keeps the stockroom in six tables of a DBAL connection. */
    public const string STORE_DBAL = 'dbal';

    /** The `store` that keeps the stockroom in memory, for tests and single runs. */
    public const string STORE_MEMORY = 'memory';

    /** The parameter that holds the service id of the connection of the stores, or null in memory. */
    public const string CONNECTION = 'phil_stockroom.connection';

    /** The DBAL adapter of each port. */
    private const array DBAL = [
        Containers::class => DbalContainers::class,
        Items::class => DbalItems::class,
        Balances::class => DbalBalances::class,
        Journal::class => DbalJournal::class,
        LoadLists::class => DbalLoadLists::class,
    ];

    /** The adapter in memory of each port. */
    private const array MEMORY = [
        Containers::class => InMemoryContainers::class,
        Items::class => InMemoryItems::class,
        Balances::class => InMemoryBalances::class,
        Journal::class => InMemoryJournal::class,
        LoadLists::class => InMemoryLoadLists::class,
    ];

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->register(StockroomTables::class, StockroomTables::class)->setArguments([$config['table_prefix']]);
        $connection = self::STORE_DBAL === $config['store'] ? \sprintf('doctrine.dbal.%s_connection', $config['connection']) : null;
        $container->setParameter(self::CONNECTION, $connection);

        foreach (null === $connection ? self::MEMORY : self::DBAL as $port => $adapter) {
            $definition = $container->register($adapter, $adapter);

            if (null === $connection) {
                $definition->addTag('kernel.reset', ['method' => 'reset']);
            } else {
                $definition->setArguments([new Reference($connection), new Reference(StockroomTables::class)]);
            }

            $container->setAlias($port, $adapter);
        }

        $container->register(Stockroom::class, Stockroom::class)
            ->setArguments([new Reference(Containers::class), new Reference(Items::class), new Reference(Balances::class), new Reference(Journal::class), new Reference($config['clock'])])
            ->setPublic(true);
        $container->setAlias(StockReader::class, Stockroom::class)->setPublic(true);
        $container->register(Deployments::class, Deployments::class)
            ->setArguments([new Reference(Stockroom::class), new Reference(LoadLists::class), new Reference(Balances::class)])
            ->setPublic(true);
        $container->register(LoadListReader::class, LoadListReader::class)->setArguments([new Reference(Items::class)])->setPublic(true);
        $container->register(StockroomTransactions::class, StockroomTransactions::class)
            ->setArguments([null === $connection ? null : new Reference($connection), new Reference(StockroomTables::class)])
            ->setPublic(true);

        if (null === $connection) {
            return;
        }

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(StockroomSchemaListener::class, StockroomSchemaListener::class)
                ->setArguments([new Reference($connection), new Reference(StockroomTables::class)])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if (class_exists(Command::class)) {
            $container->register(SchemaCommand::class, SchemaCommand::class)
                ->setArguments([new Reference($connection), new Reference(StockroomTables::class)])
                ->addTag('console.command');
        }
    }
}
