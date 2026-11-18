<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Integration\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ContentBundle\Domain\Content\SingleRoot;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\EntityManagerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The tables on MySQL 8.4, read from `PHIL_CONTENT_TEST_MYSQL_URL`, whose default collation folds case and accents: the
 * keys take `utf8mb4_bin`, and the tables of an earlier version reach it through the schema diff.
 *
 * @phpstan-import-type Params from DriverManager
 */
#[CoversClass(BinaryKeysListener::class)]
#[Group('mysql')]
final class MySqlBinaryKeysTest extends TestCase
{
    private const array BINARY = [
        'phil_content_probe_article' => [],
        'phil_content_probe_article_dimension' => ['locale', 'locale_key', 'stage'],
        'phil_content_route' => ['locale', 'resource_id', 'resource_key', 'slug'],
        'phil_content_single_root' => ['resource_id', 'resource_key'],
        'phil_content_working_copy' => ['author', 'locale', 'resource_id', 'resource_key'],
    ];

    private ?Connection $connection = null;

    #[Override]
    protected function tearDown(): void
    {
        $connection = $this->connection;

        if ($connection instanceof Connection) {
            $this->dropTables();
            $connection->close();
        }

        $this->connection = null;
    }

    public function testTheKeysAreBinaryOnTheServer(): void
    {
        $this->createTables($this->schema(true));

        self::assertSame(self::BINARY, $this->binaryColumns());
    }

    public function testTheSchemaDiffBringsTheTablesOfAnEarlierVersionToTheBinaryCollation(): void
    {
        $this->createTables($this->schema(false));
        self::assertSame(array_fill_keys(array_keys(self::BINARY), []), $this->binaryColumns());

        $connection = $this->connection();
        $manager = $connection->createSchemaManager();

        foreach ($this->schema(true)->getTables() as $table) {
            foreach ($connection->getDatabasePlatform()->getAlterTableSQL($manager->createComparator()->compareTables($manager->introspectTableByUnquotedName($this->nameOf($table)), $table)) as $statement) {
                $connection->executeStatement($statement);
            }
        }

        self::assertSame(self::BINARY, $this->binaryColumns());

        foreach ($this->schema(true)->getTables() as $table) {
            self::assertTrue($manager->createComparator()->compareTables($manager->introspectTableByUnquotedName($this->nameOf($table)), $table)->isEmpty(), $this->nameOf($table));
        }
    }

    private function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        return $this->connection = EntityManagerFactory::create($this->parameters())->getConnection();
    }

    /**
     * @return Params
     */
    private function parameters(): array
    {
        $url = getenv('PHIL_CONTENT_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_CONTENT_TEST_MYSQL_URL to run this test against a real server.');
        }

        return new DsnParser(['mysql' => 'pdo_mysql'])->parse($url);
    }

    /**
     * The schema of the bundle's tables and of a kind of content, with the listener as the bundle registers it, or
     * without it as before.
     */
    private function schema(bool $listening): Schema
    {
        $entityManager = EntityManagerFactory::create($this->parameters());

        if (!$listening) {
            foreach ($entityManager->getEventManager()->getListeners(Events::loadClassMetadata) as $listener) {
                if ($listener instanceof BinaryKeysListener) {
                    $entityManager->getEventManager()->removeEventListener(Events::loadClassMetadata, $listener);
                }
            }
        }

        return new SchemaTool($entityManager)->getSchemaFromMetadata($this->classes($entityManager));
    }

    /**
     * @return list<ClassMetadata<object>>
     */
    private function classes(EntityManager $entityManager): array
    {
        return array_map($entityManager->getClassMetadata(...), [Article::class, ArticleDimension::class, Route::class, SingleRoot::class, WorkingCopy::class]);
    }

    private function createTables(Schema $schema): void
    {
        $this->dropTables();
        $manager = $this->connection()->createSchemaManager();

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }
    }

    private function dropTables(): void
    {
        foreach (array_reverse(array_keys(self::BINARY)) as $table) {
            $this->connection()->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function binaryColumns(): array
    {
        $binary = [];

        foreach (array_keys(self::BINARY) as $table) {
            $binary[$table] = array_values(array_filter($this->connection()->fetchFirstColumn(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLLATION_NAME = 'utf8mb4_bin' ORDER BY COLUMN_NAME",
                [$table],
            ), \is_string(...)));
        }

        return $binary;
    }

    /**
     * @return non-empty-string
     */
    private function nameOf(Table $table): string
    {
        $name = $table->getObjectName()->toString();
        self::assertNotSame('', $name);

        return $name;
    }
}
