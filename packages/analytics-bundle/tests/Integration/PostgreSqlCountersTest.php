<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Integration;

use Override;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\CounterTables;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterRollup;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The counters on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell `Acme` from `acme`.
 */
#[CoversClass(CounterTables::class)]
#[CoversClass(DbalCounterStore::class)]
#[CoversClass(DbalCounterRollup::class)]
final class PostgreSqlCountersTest extends ServerCountersTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
