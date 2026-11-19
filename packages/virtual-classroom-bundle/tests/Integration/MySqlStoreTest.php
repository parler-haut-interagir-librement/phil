<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Integration;

use Override;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\ClassroomTables;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalClassroomStore::class)]
#[CoversClass(ClassroomTables::class)]
final class MySqlStoreTest extends ServerStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
