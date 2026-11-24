<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Integration;

use Override;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalResponseRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\Sql;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveyTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The bundle on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalResponseRepository::class)]
#[CoversClass(Sql::class)]
#[CoversClass(SurveyTables::class)]
final class MySqlTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
