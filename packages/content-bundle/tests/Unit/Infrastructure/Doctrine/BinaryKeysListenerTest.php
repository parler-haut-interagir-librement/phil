<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;
use Phil\ContentBundle\Domain\Content\SingleRoot;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\EntityManagerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The schema the ORM generates: the keys of the bundle's tables and of the application's rows in `utf8mb4_bin` on MySQL
 * and MariaDB, the default collation on SQLite; PostgreSQL, whose schema tool reads the server, is tested in the
 * Integration suite.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            self::assertSame([
                'phil_content_probe_article' => [],
                'phil_content_probe_article_dimension' => ['locale', 'locale_key', 'stage'],
                'phil_content_route' => ['locale', 'resource_id', 'resource_key', 'slug'],
                'phil_content_single_root' => ['resource_id', 'resource_key'],
                'phil_content_working_copy' => ['author', 'locale', 'resource_id', 'resource_key'],
            ], $this->binaryColumns($this->schema(['driver' => 'pdo_mysql', 'serverVersion' => $version])), $version);
        }
    }

    public function testTheKeysKeepTheDefaultCollationElsewhere(): void
    {
        foreach ($this->schema(['driver' => 'pdo_sqlite', 'memory' => true])->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                self::assertNull($column->getCollation(), $column->getObjectName()->toString());
            }
        }
    }

    public function testTheBaseClassOfTheRowsIsLeftAsItIs(): void
    {
        $entityManager = EntityManagerFactory::create(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']);
        $metadata = new ClassMetadata(AbstractDimensionContent::class);

        foreach (BinaryKeysListener::DIMENSION_FIELDS as $field) {
            $metadata->mapField(['fieldName' => $field, 'type' => 'string']);
        }

        new BinaryKeysListener()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $entityManager));

        foreach (BinaryKeysListener::DIMENSION_FIELDS as $field) {
            self::assertNull($metadata->getFieldMapping($field)->options, $field);
        }
    }

    /**
     * @param array{driver: 'pdo_mysql'|'pdo_sqlite', serverVersion?: string, memory?: bool} $connection
     */
    private function schema(array $connection): Schema
    {
        $entityManager = EntityManagerFactory::create($connection);

        return new SchemaTool($entityManager)->getSchemaFromMetadata(array_map(
            $entityManager->getClassMetadata(...),
            [Article::class, ArticleDimension::class, Route::class, SingleRoot::class, WorkingCopy::class],
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    private function binaryColumns(Schema $schema): array
    {
        $binary = [];

        foreach ($schema->getTables() as $table) {
            $columns = [];

            foreach ($table->getColumns() as $column) {
                if ('utf8mb4_bin' === $column->getCollation()) {
                    self::assertSame('utf8mb4', $column->getCharset());
                    $columns[] = $column->getObjectName()->toString();
                }
            }

            sort($columns);
            $binary[$table->getObjectName()->toString()] = $columns;
        }

        ksort($binary);

        return $binary;
    }
}
