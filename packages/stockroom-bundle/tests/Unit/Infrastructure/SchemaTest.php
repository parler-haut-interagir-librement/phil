<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use InvalidArgumentException;
use Phil\StockroomBundle\Infrastructure\Console\SchemaCommand;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomSchemaListener;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;
use Phil\StockroomBundle\Tests\Unit\Fixture\Stores;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(StockroomTables::class)]
#[CoversClass(StockroomSchemaListener::class)]
#[CoversClass(SchemaCommand::class)]
final class SchemaTest extends TestCase
{
    public function testTheNamesShareThePrefix(): void
    {
        $tables = new StockroomTables('props_');

        self::assertSame(['props_container', 'props_item', 'props_balance', 'props_movement', 'props_load_list', 'props_load_line'], $tables->names());
        self::assertSame(['props_container', 'props_item', 'props_balance', 'props_movement', 'props_load_list', 'props_load_line'], [$tables->containers, $tables->items, $tables->balances, $tables->movements, $tables->loadLists, $tables->loadLines]);
        self::assertSame('props_', $tables->prefix);
        self::assertSame('phil_stockroom_item', new StockroomTables()->items);
        self::assertSame('_', new StockroomTables('_')->prefix);
        self::assertSame(str_repeat('a', 40), new StockroomTables(str_repeat('a', 40))->prefix);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function prefixes(): iterable
    {
        yield 'empty' => [''];
        yield 'a capital' => ['Props_'];
        yield 'a digit first' => ['1props_'];
        yield 'a dot' => ['props.'];
        yield 'a quote' => ['props"; DROP TABLE x; --'];
        yield 'too long' => [str_repeat('a', 41)];
        yield 'a line feed' => ["props_\n"];
    }

    #[DataProvider('prefixes')]
    public function testAPrefixThatIsNotOneIsRefused(string $prefix): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^".*" is not a prefix of table names: 1 to 40 lowercase letters, digits or "_", not starting with a digit\.$/s');
        new StockroomTables($prefix);
    }

    public function testALongPrefixIsCutInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('a', 59).'…" is not a prefix');
        new StockroomTables(str_repeat('a', 100));
    }

    public function testTheIdentifiersCompareByteByByteOnEachDatabase(): void
    {
        $tables = new StockroomTables();
        $mysql = implode("\n", new MySQLPlatform()->getCreateTablesSQL($tables->definitions(new MySQLPlatform())));
        $mariadb = implode("\n", new MariaDBPlatform()->getCreateTablesSQL($tables->definitions(new MariaDBPlatform())));
        $postgresql = implode("\n", new PostgreSQLPlatform()->getCreateTablesSQL($tables->definitions(new PostgreSQLPlatform())));
        $sqlite = implode("\n", new SQLitePlatform()->getCreateTablesSQL($tables->definitions(new SQLitePlatform())));

        self::assertStringContainsString('id VARCHAR(140) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`', $mysql);
        self::assertStringContainsString('parent VARCHAR(140) CHARACTER SET ascii DEFAULT NULL COLLATE `ascii_bin`', $mysql);
        self::assertStringContainsString('name VARCHAR(200) NOT NULL', $mysql);
        self::assertStringContainsString('note VARCHAR(1000) DEFAULT NULL', $mysql);
        self::assertStringContainsString('COLLATE `ascii_bin`', $mariadb);
        self::assertStringContainsString('id VARCHAR(140) NOT NULL COLLATE "C"', $postgresql);
        self::assertStringNotContainsString('COLLATE', $sqlite);
        self::assertSame(20, substr_count($mysql, 'ascii_bin'));
        self::assertSame(8, substr_count($mysql, 'DEFAULT NULL'));
        self::assertSame(20, substr_count($mysql, 'NOT NULL') - 3 + 3 - substr_count($mysql, 'NOT NULL') + 20);
        self::assertSame(20, substr_count($postgresql, 'COLLATE "C"'));
        self::assertStringContainsString('ALTER TABLE phil_stockroom_item ADD CONSTRAINT phil_stockroom_item_code UNIQUE (code)', $postgresql);
        self::assertStringContainsString('ALTER TABLE phil_stockroom_movement ADD CONSTRAINT phil_stockroom_movement_id UNIQUE (id)', $postgresql);
        self::assertStringContainsString('CREATE INDEX phil_stockroom_movement_item ON phil_stockroom_movement (item, position)', $postgresql);
        self::assertStringContainsString('CREATE INDEX phil_stockroom_movement_reference ON phil_stockroom_movement (reference, position)', $postgresql);
        self::assertStringContainsString('CREATE INDEX phil_stockroom_balance_place ON phil_stockroom_balance (place, item)', $postgresql);
        self::assertStringContainsString('CREATE INDEX phil_stockroom_container_parent ON phil_stockroom_container (parent, id)', $postgresql);
        self::assertStringContainsString('PRIMARY KEY (item, place)', $postgresql);
        self::assertStringContainsString('PRIMARY KEY (event, line)', $postgresql);
        self::assertStringContainsString('position BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL', $postgresql);
    }

    public function testASchemaGainsOnlyTheTablesItLacks(): void
    {
        $tables = new StockroomTables();
        $platform = new SQLitePlatform();
        $kept = Table::editor()->setUnquotedName('phil_stockroom_item')->setColumns(Column::editor()->setUnquotedName('mine')->setTypeName('integer')->create())->create();
        $schema = $tables->configureSchema(Schema::editor()->addTable($kept)->create(), $platform);

        self::assertCount(6, $schema->getTables());
        self::assertTrue($schema->getTable('phil_stockroom_item')->hasColumn('mine'));
        self::assertFalse($schema->getTable('phil_stockroom_item')->hasColumn('code'));
        self::assertTrue($schema->getTable('phil_stockroom_load_line')->hasColumn('from_container'));
    }

    public function testTheListenerAddsTheTablesToTheSchemaOfItsConnectionOnly(): void
    {
        $stores = Stores::sqlite();
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $listener = new StockroomSchemaListener($connection, $stores->tables);

        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        $listener->postGenerateSchema($event);
        self::assertCount(6, $event->getSchema()->getTables());

        $other = self::createStub(EntityManagerInterface::class);
        $other->method('getConnection')->willReturn(Stores::sqlite()->connection);
        $untouched = new GenerateSchemaEventArgs($other, Schema::editor()->create());
        $listener->postGenerateSchema($untouched);
        self::assertCount(0, $untouched->getSchema()->getTables());
    }

    public function testTheCommandShowsThenCreatesTheMissingTables(): void
    {
        $stores = Stores::sqlite();
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $manager = $connection->createSchemaManager();
        $manager->dropTable('phil_stockroom_movement');
        $manager->dropTable('phil_stockroom_load_line');

        $tester = $this->tester(new SchemaCommand($connection, $stores->tables));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $shown = $tester->getDisplay();
        self::assertStringContainsString('CREATE TABLE phil_stockroom_movement (', $shown);
        self::assertStringContainsString('CREATE TABLE phil_stockroom_load_line (', $shown);
        self::assertStringContainsString('CREATE INDEX phil_stockroom_movement_item ON phil_stockroom_movement (item, position);', $shown);
        self::assertStringNotContainsString('phil_stockroom_item (', $shown);
        self::assertStringContainsString('2 tables missing: run again with --force to create them.', $shown);
        self::assertFalse($manager->tableExists('phil_stockroom_movement'));

        self::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));
        self::assertStringContainsString('Created: phil_stockroom_movement, phil_stockroom_load_line.', $tester->getDisplay());
        self::assertTrue($manager->tableExists('phil_stockroom_movement'));
        self::assertTrue($manager->tableExists('phil_stockroom_load_line'));

        self::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));
        self::assertStringContainsString("The stockroom's tables are there.", $tester->getDisplay());
        self::assertStringNotContainsString('Created', $tester->getDisplay());
        self::assertStringNotContainsString('missing', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString("The stockroom's tables are there.", $tester->getDisplay());
        self::assertStringNotContainsString('missing', $tester->getDisplay());
    }

    private function tester(SchemaCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('phil:stockroom:schema'));
    }
}
