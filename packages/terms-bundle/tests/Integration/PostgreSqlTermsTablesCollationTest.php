<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Integration;

use Override;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(TermsTables::class)]
final class PostgreSqlTermsTablesCollationTest extends ServerTermsTablesCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
