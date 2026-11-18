<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\DbalPlacementStore;
use PHPUnit\Framework\TestCase;

/**
 * The placements on a real server read from the environment, in its test database: the tenants `Rose`, `rose`, `ROSE`
 * and `rosé` are four tenants, each in its own database, whatever the collation of the server. Unlike the other tests
 * of the stores, this one creates no database, so that the account of the tests is enough.
 */
abstract class ServerPlacementKeysTestCase extends TestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testTenantsThatDifferByCaseOrAccentArePlacedApart(): void
    {
        $store = new DbalPlacementStore($this->connection());
        $tenants = ['Rose' => 'db_rose_title', 'rose' => 'db_rose', 'ROSE' => 'db_rose_upper', 'rosé' => 'db_rose_accent'];

        foreach ($tenants as $tenant => $database) {
            $store->place(TenantId::fromString($tenant), Placement::dedicated($database));
        }

        foreach ($tenants as $tenant => $database) {
            self::assertSame($database, $store->placementOf(TenantId::fromString($tenant))->database, $tenant);
        }

        self::assertCount(4, iterator_to_array($store->placedTenants(), false));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    private function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->connection = $connection;
        $connection->executeStatement('DROP TABLE IF EXISTS '.StoreTables::PLACEMENTS);
        $manager = $connection->createSchemaManager();

        foreach (StoreTables::configure(Schema::editor()->create(), suspensions: false)->getTables() as $table) {
            $manager->createTable($table);
        }

        return $connection;
    }
}
