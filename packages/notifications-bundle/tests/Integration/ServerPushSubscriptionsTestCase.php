<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use Phil\NotificationsBundle\Tests\Unit\Infrastructure\PushSubscriptionsTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The DBAL store of push subscriptions on a real server: the table created from `NotificationTables`, dropped first.
 */
abstract class ServerPushSubscriptionsTestCase extends PushSubscriptionsTestCase
{
    private ?MockClock $clock = null;

    /**
     * The URL of the server, or `null` to skip the test.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function subscriptions(int $limit = 10): PushSubscriptions
    {
        $url = $this->url();

        if (null === $url || '' === $url) {
            self::markTestSkipped('Start the servers with `phil-dev services up` and run `phil-dev services test` to run this test.');
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->create($connection);
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');

        return new DbalPushSubscriptions($connection, $this->clock, $limit);
    }

    #[Override]
    protected function later(): void
    {
        $this->clock?->sleep(1);
    }

    private function create(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS '.NotificationTables::PUSH_SUBSCRIPTIONS);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(NotificationTables::configure(Schema::editor()->create(), false, true)->getTables()) as $sql) {
            if (str_contains($sql, NotificationTables::PUSH_SUBSCRIPTIONS)) {
                $connection->executeStatement($sql);
            }
        }
    }
}
