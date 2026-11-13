<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Integration;

use Override;
use Phil\Automations\Dbal\DbalRunStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL run store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalRunStore::class)]
final class MySqlRunStoreTest extends ServerRunStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
