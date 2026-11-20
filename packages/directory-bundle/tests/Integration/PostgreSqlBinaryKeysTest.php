<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Integration;

use Override;
use Phil\DirectoryBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The listing table and the table of its rows on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default
 * collation is deterministic: the columns keep it and still tell the values apart.
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
