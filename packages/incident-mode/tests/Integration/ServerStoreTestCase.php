<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Dbal\DbalActivationStore;
use Phil\IncidentMode\Dbal\IncidentTables;
use Phil\IncidentMode\Tests\Unit\ActivationStoreTestCase;

/**
 * The DBAL store on a real server: the conditional updates, the limits of `modifyLimitQuery()`, the moments written
 * and read in UTC, the table created from `IncidentTables::table()`.
 */
abstract class ServerStoreTestCase extends ActivationStoreTestCase
{
    /**
     * The URL of the server, or `null` to skip the test.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function store(): ActivationStore
    {
        $url = $this->url();

        if (null === $url || '' === $url) {
            self::markTestSkipped('Start the servers with `phil-dev services up` and run `phil-dev services test` to run this test.');
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $manager = $connection->createSchemaManager();

        if ($manager->tablesExist([IncidentTables::ACTIVATIONS])) {
            $manager->dropTable(IncidentTables::ACTIVATIONS);
        }

        $manager->createTable(IncidentTables::table());

        return new DbalActivationStore($connection);
    }
}
