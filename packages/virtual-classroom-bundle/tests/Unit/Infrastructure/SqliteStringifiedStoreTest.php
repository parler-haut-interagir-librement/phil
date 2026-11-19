<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Override;
use PDO;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The same promises on a driver that gives every value as a string, as MySQL does when it does not use native types.
 */
#[CoversClass(DbalClassroomStore::class)]
final class SqliteStringifiedStoreTest extends StoreTestCase
{
    #[Override]
    protected function store(): ClassroomStore
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'driverOptions' => [PDO::ATTR_STRINGIFY_FETCHES => true]]);
        $store = new DbalClassroomStore($connection);
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }
}
