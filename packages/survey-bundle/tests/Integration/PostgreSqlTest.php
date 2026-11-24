<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Integration;

use Override;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalResponseRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\Sql;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveyTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The bundle on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalResponseRepository::class)]
#[CoversClass(Sql::class)]
#[CoversClass(SurveyTables::class)]
final class PostgreSqlTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
