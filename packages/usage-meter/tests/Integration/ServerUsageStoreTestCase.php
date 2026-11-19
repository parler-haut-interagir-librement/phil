<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\UsageMeter\Counter;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use Phil\UsageMeter\Increment;
use Phil\UsageMeter\Tests\Unit\UsageStoreTestCase;
use Phil\UsageMeter\UsageStore;

/**
 * Every promise of a store, on a real server read from the environment: the insert that ignores a conflict in that
 * server's dialect, the conditional increment, the refund under a row lock.
 */
abstract class ServerUsageStoreTestCase extends UsageStoreTestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testARefundAndAnIncrementOfAnotherConnectionBothCount(): void
    {
        $store = $this->store();
        $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 3, 5, 'k');

        $other = $this->connect();
        $other->beginTransaction();

        self::assertSame(Increment::Recorded, new DbalUsageStore($other)->add(new Counter('a', 'm', self::at(0), self::at(10)), 2, 5));

        $other->commit();
        $other->close();

        self::assertSame(3, $store->refund('a', 'm', 'k'));
        self::assertSame(2, $store->total('a', 'm', self::at(0), self::at(10)));
        self::assertSame(Increment::Recorded, $store->add(new Counter('a', 'm', self::at(0), self::at(10)), 3, 5));
    }

    #[Override]
    protected function store(): UsageStore
    {
        $connection = $this->connection ??= $this->connect();
        $store = new DbalUsageStore($connection);

        foreach ([DbalUsageStore::COUNTER_TABLE, DbalUsageStore::KEY_TABLE] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    private function connect(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
