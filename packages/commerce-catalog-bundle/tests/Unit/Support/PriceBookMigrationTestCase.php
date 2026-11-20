<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceCatalogBundle\Application\PriceBookOrder;
use Phil\CommerceCatalogBundle\Domain\Model\PriceBook;
use Phil\CommerceCatalogBundle\Infrastructure\Doctrine\PriceBookOrderMigration;
use PHPUnit\Framework\TestCase;

/**
 * The move from books ordered by `priority` to books ordered by their `after` lists, on a database: the table of the
 * numeric model is built, filled, migrated, then compared with the table the mapping declares, and the books are
 * ordered as they were.
 */
abstract class PriceBookMigrationTestCase extends TestCase
{
    private EntityManager $entityManager;

    private Connection $connection;

    /**
     * The URL of the database, or `null` to skip.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function setUp(): void
    {
        $url = $this->url();

        if (null === $url) {
            self::markTestSkipped('No server is named for this test.');
        }

        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite'])->parse($url);
        $this->connection = DriverManager::getConnection($parameters);
        $this->entityManager = new EntityManager($this->connection, $configuration);

        $metadata = [$this->entityManager->getClassMetadata(PriceBook::class)];
        $tool = new SchemaTool($this->entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->toNumericModel();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            new SchemaTool($this->entityManager)->dropSchema([$this->entityManager->getClassMetadata(PriceBook::class)]);
            $this->connection->close();
        }
    }

    public function testTheTableEndsAsTheMappingDeclaresIt(): void
    {
        $this->insert(['retail' => 0, 'wholesale' => 10]);

        self::assertTrue(PriceBookOrderMigration::run($this->connection));

        $schema = $this->connection->createSchemaManager();
        $mapped = new SchemaTool($this->entityManager)->getSchemaFromMetadata([$this->entityManager->getClassMetadata(PriceBook::class)])->getTable(PriceBookOrderMigration::TABLE);
        $diff = $schema->createComparator()->compareTables($schema->introspectTableByUnquotedName(PriceBookOrderMigration::TABLE), $mapped);

        self::assertTrue($diff->isEmpty());
        self::assertFalse(PriceBookOrderMigration::run($this->connection));
    }

    /**
     * Each book follows every book of a higher priority; books of one priority stay by code, withdrawn ones included,
     * so that withdrawing a book never breaks the order of the others.
     */
    public function testTheBooksKeepTheOrderTheyWereTriedIn(): void
    {
        $this->insert(['everyone' => 0, 'black-friday' => 50, 'wholesale' => 10, 'vip' => 10, 'staff' => 50, 'old' => 30, 'clearance' => -5], ['old']);

        PriceBookOrderMigration::run($this->connection);

        $books = $this->entityManager->getRepository(PriceBook::class)->findBy([], ['code' => 'ASC']);
        self::assertSame(['black-friday', 'staff', 'old', 'vip', 'wholesale', 'everyone', 'clearance'], array_map(static fn (PriceBook $book): string => $book->code, PriceBookOrder::sort($books)));
        self::assertSame(['black-friday', 'staff', 'old', 'vip', 'wholesale'], $this->entityManager->find(PriceBook::class, 'everyone')?->after());
        self::assertSame([], $this->entityManager->find(PriceBook::class, 'staff')?->after());

        $active = $this->entityManager->getRepository(PriceBook::class)->findBy(['active' => true], ['code' => 'ASC']);
        self::assertSame(['black-friday', 'staff', 'vip', 'wholesale', 'everyone', 'clearance'], array_map(static fn (PriceBook $book): string => $book->code, PriceBookOrder::sort($active)));
    }

    public function testAnIndexOnActiveAloneIsKeptAndNoIndexKeepsThePriority(): void
    {
        $schema = $this->connection->createSchemaManager();
        $table = $schema->introspectTableByUnquotedName(PriceBookOrderMigration::TABLE);
        $schema->alterTable($schema->createComparator()->compareTables($table, $table->edit()->addIndex(Index::editor()->setUnquotedName('idx_kept_active')->addUnquotedColumnName('active')->create())->create()));
        $this->insert(['retail' => 0]);

        PriceBookOrderMigration::run($this->connection);

        $indexes = [];

        foreach ($schema->introspectTableByUnquotedName(PriceBookOrderMigration::TABLE)->getIndexes() as $index) {
            $indexes[strtolower($index->getObjectName()->getIdentifier()->getValue())] = array_map(static fn (IndexedColumn $column): string => strtolower($column->getColumnName()->getIdentifier()->getValue()), $index->getIndexedColumns());
        }

        self::assertSame(['active'], $indexes['idx_kept_active']);
        self::assertArrayNotHasKey(PriceBook::ACTIVE_INDEX, $indexes);
        self::assertArrayNotHasKey('idx_numeric_order', $indexes);

        foreach ($indexes as $columns) {
            self::assertNotContains('priority', $columns);
        }
    }

    /**
     * A book keeps at most `PriceBook::MAX_AFTER` books before it: the ones tried just before it.
     */
    public function testALongListKeepsTheNearestBooks(): void
    {
        $count = PriceBook::MAX_AFTER + 2;
        $priorities = [];

        for ($i = 0; $i < $count; ++$i) {
            $priorities[\sprintf('b%04d', $i)] = $count - $i;
        }

        $this->insert($priorities);

        PriceBookOrderMigration::run($this->connection);

        $after = $this->entityManager->find(PriceBook::class, \sprintf('b%04d', $count - 1))?->after() ?? [];

        self::assertCount(PriceBook::MAX_AFTER, $after);
        self::assertSame('b0001', $after[0]);
        self::assertSame(\sprintf('b%04d', $count - 2), $after[PriceBook::MAX_AFTER - 1]);
    }

    /**
     * A priority read back as a decimal counts as its integer part.
     */
    public function testAPriorityCountsAsAnInteger(): void
    {
        $this->insert(['a' => 10, 'b' => 10]);
        $this->connection->executeStatement('UPDATE '.PriceBookOrderMigration::TABLE." SET priority = 10.5 WHERE code = 'b'");

        // MySQL and PostgreSQL round the decimal into their integer column themselves: only SQLite keeps it.
        if (!\in_array($this->connection->fetchOne('SELECT priority FROM '.PriceBookOrderMigration::TABLE." WHERE code = 'b'"), ['10.5', 10.5], true)) {
            self::markTestSkipped('The column holds integers only: the database rounded the decimal itself.');
        }

        PriceBookOrderMigration::run($this->connection);

        self::assertSame([], $this->entityManager->find(PriceBook::class, 'a')?->after());
        self::assertSame([], $this->entityManager->find(PriceBook::class, 'b')?->after());
    }

    public function testAnEmptyTableIsMigrated(): void
    {
        self::assertTrue(PriceBookOrderMigration::run($this->connection));
        self::assertEquals(0, $this->connection->fetchOne('SELECT COUNT(*) FROM '.PriceBookOrderMigration::TABLE));
    }

    /**
     * The table as the numeric model declared it: a `priority` column, indexed with `active`, and no `after_books`.
     */
    private function toNumericModel(): void
    {
        $schema = $this->connection->createSchemaManager();
        $table = $schema->introspectTableByUnquotedName(PriceBookOrderMigration::TABLE);
        $editor = $table->edit()
            ->dropColumnByUnquotedName('after_books')
            ->addColumn(Column::editor()->setUnquotedName('priority')->setTypeName('integer')->create());

        foreach ($table->getIndexes() as $index) {
            if (PriceBook::ACTIVE_INDEX === strtolower($index->getObjectName()->getIdentifier()->getValue())) {
                $editor->dropIndex($index->getObjectName());
            }
        }

        $editor->addIndex(Index::editor()->setUnquotedName('idx_numeric_order')->addUnquotedColumnName('active')->addUnquotedColumnName('priority')->create());
        $schema->alterTable($schema->createComparator()->compareTables($table, $editor->create()));
    }

    /**
     * @param array<string, int> $priorities code → priority
     * @param list<string>       $withdrawn
     */
    private function insert(array $priorities, array $withdrawn = []): void
    {
        foreach ($priorities as $code => $priority) {
            $this->connection->insert(PriceBookOrderMigration::TABLE, [
                'code' => $code,
                'name' => ucfirst($code),
                'priority' => $priority,
                'currency' => 'CAD',
                'tiers' => '[]',
                'active' => !\in_array($code, $withdrawn, true),
            ], ['active' => 'boolean']);
        }
    }
}
