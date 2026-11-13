<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Integration;

use Override;
use Phil\AccessLinks\Dbal\DbalInvitationStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalInvitationStore::class)]
final class MySqlInvitationStoreTest extends ServerInvitationStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
