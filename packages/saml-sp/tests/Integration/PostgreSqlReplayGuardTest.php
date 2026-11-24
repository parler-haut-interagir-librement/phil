<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Integration;

use Override;
use Phil\SamlSp\Store\DbalReplayGuard;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The database replay guard on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalReplayGuard::class)]
final class PostgreSqlReplayGuardTest extends ServerReplayGuardTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
