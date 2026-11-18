<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Integration;

use Override;
use Phil\CheckinBundle\Infrastructure\Doctrine\DoctrineCheckInLedger;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(DoctrineCheckInLedger::class)]
final class PostgreSqlCheckInLedgerCollationTest extends ServerCheckInLedgerCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
