<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Integration;

use Override;
use Phil\AccessCodes\Dbal\DbalCodeStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalCodeStore::class)]
final class MySqlCodeStoreTest extends ServerCodeStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
