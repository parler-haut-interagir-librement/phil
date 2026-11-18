<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Integration;

use Override;
use Phil\MediaBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
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
