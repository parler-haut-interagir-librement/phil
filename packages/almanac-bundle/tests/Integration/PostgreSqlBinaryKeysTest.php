<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Integration;

use Override;
use Phil\AlmanacBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The table on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * column keeps it and still tells the keys apart.
 */
#[CoversClass(BinaryKeysListener::class)]
final class PostgreSqlBinaryKeysTest extends ServerBinaryKeysTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
