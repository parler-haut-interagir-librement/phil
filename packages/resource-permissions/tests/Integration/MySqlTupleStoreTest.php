<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Integration;

use Override;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalTupleStore::class)]
final class MySqlTupleStoreTest extends ServerTupleStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
