<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit;

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
use Phil\StockroomBundle\Infrastructure\DependencyInjection\StockroomServices;
use Phil\StockroomBundle\PhilStockroomBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilStockroomBundle::class)]
#[CoversClass(StockroomServices::class)]
final class PhilStockroomBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_stockroom', $this->extension()->getAlias());
    }

    public function testTheStoresAreInTheTablesOfTheDefaultConnection(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');
        $tables = new Reference(StockroomTables::class);

        self::assertSame(['phil_stockroom_'], $container->getDefinition(StockroomTables::class)->getArguments());
        self::assertSame('doctrine.dbal.default_connection', $container->getParameter(StockroomServices::CONNECTION));

        foreach ([Containers::class => DbalContainers::class, Items::class => DbalItems::class, Balances::class => DbalBalances::class, Journal::class => DbalJournal::class, LoadLists::class => DbalLoadLists::class] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertEquals([$connection, $tables], $container->getDefinition($adapter)->getArguments(), $adapter);
            self::assertSame([], $container->getDefinition($adapter)->getTags(), $adapter);
        }

        self::assertEquals([new Reference(Containers::class), new Reference(Items::class), new Reference(Balances::class), new Reference(Journal::class), new Reference('clock')], $container->getDefinition(Stockroom::class)->getArguments());
        self::assertTrue($container->getDefinition(Stockroom::class)->isPublic());
        self::assertSame(Stockroom::class, (string) $container->getAlias(StockReader::class));
        self::assertTrue($container->getAlias(StockReader::class)->isPublic());
        self::assertEquals([new Reference(Stockroom::class), new Reference(LoadLists::class), new Reference(Balances::class)], $container->getDefinition(Deployments::class)->getArguments());
        self::assertTrue($container->getDefinition(Deployments::class)->isPublic());
        self::assertEquals([new Reference(Items::class)], $container->getDefinition(LoadListReader::class)->getArguments());
        self::assertTrue($container->getDefinition(LoadListReader::class)->isPublic());
        self::assertEquals([$connection, $tables], $container->getDefinition(StockroomTransactions::class)->getArguments());
        self::assertTrue($container->getDefinition(StockroomTransactions::class)->isPublic());
        self::assertEquals([$connection, $tables], $container->getDefinition(StockroomSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(StockroomSchemaListener::class)->getTags());
        self::assertEquals([$connection, $tables], $container->getDefinition(SchemaCommand::class)->getArguments());
        self::assertSame(['console.command' => [[]]], $container->getDefinition(SchemaCommand::class)->getTags());
    }

    public function testAnotherConnectionPrefixAndClock(): void
    {
        $container = $this->load([['connection' => 'stock', 'table_prefix' => 'props_', 'clock' => 'app.clock']]);

        self::assertSame(['props_'], $container->getDefinition(StockroomTables::class)->getArguments());
        self::assertSame('doctrine.dbal.stock_connection', $container->getParameter(StockroomServices::CONNECTION));
        self::assertEquals(new Reference('doctrine.dbal.stock_connection'), $container->getDefinition(DbalItems::class)->getArgument(0));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(Stockroom::class)->getArgument(4));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'stock']], $container->getDefinition(StockroomSchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testTheStoreCanBeNamedDbal(): void
    {
        $container = $this->load([['store' => 'dbal']]);

        self::assertSame('doctrine.dbal.default_connection', $container->getParameter(StockroomServices::CONNECTION));
        self::assertTrue($container->hasDefinition(DbalItems::class));
    }

    public function testTheStoresInMemory(): void
    {
        $container = $this->load([['store' => 'memory']]);

        self::assertNull($container->getParameter(StockroomServices::CONNECTION));

        foreach ([Containers::class => InMemoryContainers::class, Items::class => InMemoryItems::class, Balances::class => InMemoryBalances::class, Journal::class => InMemoryJournal::class, LoadLists::class => InMemoryLoadLists::class] as $port => $adapter) {
            self::assertSame($adapter, (string) $container->getAlias($port));
            self::assertSame([], $container->getDefinition($adapter)->getArguments(), $adapter);
            self::assertSame([['method' => 'reset']], $container->getDefinition($adapter)->getTag('kernel.reset'), $adapter);
        }

        self::assertEquals([null, new Reference(StockroomTables::class)], $container->getDefinition(StockroomTransactions::class)->getArguments());
        self::assertTrue($container->hasDefinition(Stockroom::class));
        self::assertFalse($container->hasDefinition(DbalItems::class));
        self::assertFalse($container->hasDefinition(StockroomSchemaListener::class));
        self::assertFalse($container->hasDefinition(SchemaCommand::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalid(): iterable
    {
        yield 'unknown store' => [['store' => 'redis']];
        yield 'connection with a dot' => [['connection' => 'a.b']];
        yield 'empty connection' => [['connection' => '']];
        yield 'connection too long' => [['connection' => str_repeat('a', 65)]];
        yield 'connection with a line feed' => [['connection' => "stock\n"]];
        yield 'prefix with a capital' => [['table_prefix' => 'Stock_']];
        yield 'prefix starting with a digit' => [['table_prefix' => '1stock_']];
        yield 'prefix too long' => [['table_prefix' => str_repeat('a', 41)]];
        yield 'prefix with a line feed' => [['table_prefix' => "stock_\n"]];
        yield 'prefix not a text' => [['table_prefix' => 12]];
        yield 'connection not a text' => [['connection' => 12]];
        yield 'empty clock' => [['clock' => '']];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testAnInvalidConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([$config]);
    }

    public function testTheLongestNamesAreAccepted(): void
    {
        $container = $this->load([['connection' => str_repeat('a', 64), 'table_prefix' => '_'.str_repeat('a', 39)]]);

        self::assertSame(['_'.str_repeat('a', 39)], $container->getDefinition(StockroomTables::class)->getArguments());
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
        $extension = new PhilStockroomBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
