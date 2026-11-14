<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Stockroom\Contract\StockReader;
use Phil\Stockroom\Deployments;
use Phil\Stockroom\Holding;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\LoadListReader;
use Phil\Stockroom\Stockroom;
use Phil\StockroomBundle\Infrastructure\Console\SchemaCommand;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomSchemaListener;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;
use Phil\StockroomBundle\Infrastructure\DependencyInjection\StockroomServices;
use Phil\StockroomBundle\PhilStockroomBundle;
use Phil\StockroomBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The bundle in an application: the tables join the schema the ORM generates, the stockroom and its deployments run on
 * them in a transaction, and the schema command is there.
 */
#[CoversClass(PhilStockroomBundle::class)]
#[CoversClass(StockroomServices::class)]
#[CoversClass(StockroomSchemaListener::class)]
#[CoversClass(SchemaCommand::class)]
final class StockroomBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $tool = new SchemaTool($entityManager);
        $connection = $this->service('doctrine.dbal.default_connection', Connection::class);

        foreach (['props_load_line', 'props_load_list', 'props_movement', 'props_balance', 'props_item', 'props_container'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $tool->createSchema([]);
    }

    public function testTheTablesJoinTheSchemaOfTheOrm(): void
    {
        $manager = $this->service('doctrine.dbal.default_connection', Connection::class)->createSchemaManager();

        foreach (['props_container', 'props_item', 'props_balance', 'props_movement', 'props_load_list', 'props_load_line'] as $table) {
            self::assertTrue($manager->tableExists($table), $table);
        }
    }

    public function testTheStockroomAndItsDeploymentsRunInATransaction(): void
    {
        $stockroom = $this->service(Stockroom::class, Stockroom::class);
        $deployments = $this->service(Deployments::class, Deployments::class);
        $transactions = $this->service(StockroomTransactions::class, StockroomTransactions::class);
        $stockroom->addContainer('Shelf', id: 'shelf');
        $stockroom->addItem('SW-1', 'Foam sword', home: 'shelf', id: 'sword');

        $transactions->run(static fn (): mixed => $stockroom->receive('sword', 'shelf', 40, 'order-1'), 'sword');
        $deployments->plan('larp-7', $this->service(LoadListReader::class, LoadListReader::class)->read("12 SW-1\n"));
        $deployed = $transactions->run(static fn (): mixed => $deployments->deploy('larp-7'), 'sword');

        self::assertTrue($deployed->isComplete());
        self::assertSame(28, $this->service(StockReader::class, Stockroom::class)->available('sword'));
        self::assertSame([['event/larp-7', 12]], array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $deployments->outstanding('larp-7')));
        self::assertTrue($transactions->uses($this->service('doctrine.dbal.default_connection', Connection::class)));
        self::assertEquals([new LoadLine('sword', 12)], $deployments->loadList('larp-7')->lines);
        self::assertSame('2026-11-13 10:00:00', $stockroom->history('sword')[0]->at->format('Y-m-d H:i:s'));
    }

    public function testTheSchemaCommandSaysTheTablesAreThere(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $tester = new CommandTester(new Application($kernel)->find('phil:stockroom:schema'));
        $tester->execute([]);

        self::assertStringContainsString("The stockroom's tables are there.", $tester->getDisplay());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
