<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Integration;

use Override;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalUsageStore::class)]
final class MySqlUsageStoreTest extends ServerUsageStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
