<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Integration;

use Override;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPushSubscriptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * PostgreSQL, through `pdo_pgsql`: `phil-dev services test` gives it `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalPushSubscriptions::class)]
#[Group('postgresql')]
final class PostgreSqlPushSubscriptionsTest extends ServerPushSubscriptionsTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) ? $url : null;
    }
}
