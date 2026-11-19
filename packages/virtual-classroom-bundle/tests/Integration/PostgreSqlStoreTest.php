<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Integration;

use Override;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\ClassroomTables;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalClassroomStore::class)]
#[CoversClass(ClassroomTables::class)]
final class PostgreSqlStoreTest extends ServerStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
