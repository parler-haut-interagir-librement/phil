<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\ContentBundle\Domain\Content\DimensionContentId;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\DimensionContentIdType;
use Phil\DirectoryBundle\Domain\ValueObject\ListingId;
use Phil\DirectoryBundle\Infrastructure\Doctrine\ListingIdType;
use Phil\DirectoryBundle\Tests\Fixture\ListingSchema;
use PHPUnit\Framework\TestCase;

/**
 * The listing table and the table of its rows the ORM generates on a real server read from the environment: the
 * listings of the authors `ann`, `Ann`, `ANN` and `ánn`, in the genres of the same names, and the versions they wrote,
 * are each found by their own author, genre and writer only.
 */
abstract class ServerBinaryKeysTestCase extends TestCase
{
    /**
     * Values that differ only by case or by an accent.
     */
    protected const array VALUES = ['ann', 'Ann', 'ANN', 'ánn'];

    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testValuesThatDifferByCaseOrAccentAreKeptApart(): void
    {
        $connection = $this->connection();
        $this->createTables($this->schema(true));

        foreach (self::VALUES as $value) {
            $listing = ListingId::generate();
            $connection->insert(ListingSchema::LISTING_TABLE, ['id' => $listing, 'genre' => $value, 'author' => $value, 'editors' => '[]', 'indexing' => 'listed', 'site_listed' => true], ['id' => ListingIdType::NAME, 'site_listed' => 'boolean']);
            $connection->insert(ListingSchema::ROW_TABLE, ['id' => DimensionContentId::generate(), 'root_id' => $listing, 'locale_key' => '', 'stage' => 'draft', 'version' => 1, 'edited_by' => $value], ['id' => DimensionContentIdType::NAME, 'root_id' => ListingIdType::NAME]);
        }

        foreach (self::VALUES as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT author FROM phil_directory_listing WHERE author = ?', [$value]), $value);
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT genre FROM phil_directory_listing WHERE genre = ?', [$value]), $value);
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT edited_by FROM phil_directory_listing_dimension WHERE edited_by = ?', [$value]), $value);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    protected function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }

    /**
     * The schema of the listing table and of its rows, with the listener as the bundle registers it, or without it as
     * before.
     */
    protected function schema(bool $listening): Schema
    {
        return ListingSchema::of($this->connection(), $listening);
    }

    /**
     * The listing table and the table of its rows, created afresh: only those, since the server is shared with the tests of other packages.
     */
    protected function createTables(Schema $schema): void
    {
        $connection = $this->connection();
        $manager = $connection->createSchemaManager();

        foreach (array_reverse($schema->getTables()) as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toString());
        }

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }
    }
}
