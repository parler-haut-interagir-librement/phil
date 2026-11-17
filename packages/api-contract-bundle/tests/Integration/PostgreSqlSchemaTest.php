<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Integration;

use Override;
use Phil\ApiContractBundle\Infrastructure\Doctrine\IdempotencySchemaListener;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The table of idempotency keys on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(IdempotencySchemaListener::class)]
final class PostgreSqlSchemaTest extends ServerSchemaTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
