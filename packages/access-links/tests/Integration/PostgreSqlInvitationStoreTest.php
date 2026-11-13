<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Integration;

use Override;
use Phil\AccessLinks\Dbal\DbalInvitationStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalInvitationStore::class)]
final class PostgreSqlInvitationStoreTest extends ServerInvitationStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
