<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\ContentBundle\Domain\Content\DimensionContentId;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\DimensionContentIdType;
use Phil\DirectoryBundle\Domain\ValueObject\ListingId;
use Phil\DirectoryBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\DirectoryBundle\Infrastructure\Doctrine\ListingIdType;
use Phil\DirectoryBundle\Tests\Fixture\ListingSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The schema the ORM generates: the genre, the author and who wrote a version in `utf8mb4_bin` on MySQL and MariaDB, the default collation
 * on SQLite, which compares bytes and keeps values that differ only by case or accent apart; PostgreSQL, whose schema
 * tool reads the server, is tested in the Integration suite.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    public function testTheGenreTheAuthorAndWhoWroteAVersionAreBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $version]);

            self::assertSame([ListingSchema::LISTING_TABLE => ['author', 'genre'], ListingSchema::ROW_TABLE => ['edited_by']], $this->binaryColumns(ListingSchema::of($connection, true)), $version);
            self::assertSame([ListingSchema::LISTING_TABLE => [], ListingSchema::ROW_TABLE => []], $this->binaryColumns(ListingSchema::of($connection, false)), $version);
        }
    }

    public function testTheyKeepTheDefaultCollationOnSqliteWhichKeepsValuesApart(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (ListingSchema::of($connection, true)->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                self::assertNull($column->getCollation(), $column->getObjectName()->toString());
            }

            $connection->createSchemaManager()->createTable($table);
        }

        $values = ['ann', 'Ann', 'ANN', 'ánn'];

        foreach ($values as $value) {
            $listing = ListingId::generate();
            $connection->insert(ListingSchema::LISTING_TABLE, ['id' => $listing, 'genre' => $value, 'author' => $value, 'editors' => '[]', 'indexing' => 'listed', 'site_listed' => true], ['id' => ListingIdType::NAME, 'site_listed' => 'boolean']);
            $connection->insert(ListingSchema::ROW_TABLE, ['id' => DimensionContentId::generate(), 'root_id' => $listing, 'locale_key' => '', 'stage' => 'draft', 'version' => 1, 'edited_by' => $value], ['id' => DimensionContentIdType::NAME, 'root_id' => ListingIdType::NAME]);
        }

        foreach ($values as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT author FROM phil_directory_listing WHERE author = ?', [$value]), $value);
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT genre FROM phil_directory_listing WHERE genre = ?', [$value]), $value);
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT edited_by FROM phil_directory_listing_dimension WHERE edited_by = ?', [$value]), $value);
        }
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
