<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Integration;

use Override;
use Phil\Habits\Dbal\DbalCheckInStore;
use Phil\Habits\Dbal\DbalHabitStore;
use Phil\Habits\Dbal\TableName;
use Phil\Habits\Tests\Unit\Dbal\DbalStoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The stores on a PostgreSQL server.
 */
#[CoversClass(DbalHabitStore::class)]
#[CoversClass(DbalCheckInStore::class)]
#[CoversClass(TableName::class)]
#[Group('mysql')]
final class PostgreSqlStoreTest extends DbalStoreTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) && '' !== $url ? $url : null;
    }
}
