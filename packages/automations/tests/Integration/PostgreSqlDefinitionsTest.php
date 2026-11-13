<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Integration;

use Override;
use Phil\Automations\Dbal\DbalDefinitions;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL definitions on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalDefinitions::class)]
final class PostgreSqlDefinitionsTest extends ServerDefinitionsTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
