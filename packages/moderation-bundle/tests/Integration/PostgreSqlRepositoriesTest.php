<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalDecisionRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalMemberNoteRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalReportRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalSandboxRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalStatementRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use Phil\ModerationBundle\Infrastructure\Doctrine\Rows;
use Phil\ModerationBundle\Tests\Unit\Infrastructure\RepositoriesTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The stores on PostgreSQL 17, through `pdo_pgsql`: `phil-dev services test` gives it `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalReportRepository::class)]
#[CoversClass(DbalDecisionRepository::class)]
#[CoversClass(DbalMemberNoteRepository::class)]
#[CoversClass(DbalSandboxRepository::class)]
#[CoversClass(DbalStatementRepository::class)]
#[CoversClass(ModerationTables::class)]
#[CoversClass(Rows::class)]
#[Group('postgresql')]
final class PostgreSqlRepositoriesTest extends RepositoriesTestCase
{
    #[Override]
    protected function connection(): Connection
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Start the servers with `phil-dev services up` and run `phil-dev services test` to run this test.');
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
