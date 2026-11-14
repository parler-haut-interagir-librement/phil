<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Integration;

use Override;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPushSubscriptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * MySQL 8, through `pdo_mysql`: `phil-dev services test` gives it `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalPushSubscriptions::class)]
#[Group('mysql')]
final class MySqlPushSubscriptionsTest extends ServerPushSubscriptionsTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        return \is_string($url) ? $url : null;
    }
}
