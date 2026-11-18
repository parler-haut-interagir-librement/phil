<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Integration;

use Override;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(NotificationTables::class)]
final class PostgreSqlNotificationTablesCollationTest extends ServerNotificationTablesCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
