<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Override;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\CreateTenantDatabaseCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrateTenantDatabasesCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrationTargets;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MoveTenantCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Database\TenantDatabaseCreator;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantConnectionSwitch;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseDriver;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseMiddleware;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use Phil\TenancyDatabaseBundle\Infrastructure\Http\RefuseUnavailableTenant;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\CopyPlan;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\TenantMover;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 *     PHIL_TENANCY_DATABASE_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' vendor/bin/phpunit tests/Integration.
 *
 * `PHIL_TEST_POSTGRESQL_URL`, which `phil-dev services env` prints, is read when the first is not set; its account must be
 * allowed to create databases, as the server's own user is.
 */
#[CoversClass(TenantConnectionSwitch::class)]
#[CoversClass(TenantDatabaseRouter::class)]
#[CoversClass(TenantDatabaseMiddleware::class)]
#[CoversClass(TenantDatabaseDriver::class)]
#[CoversClass(TenantDatabaseCreator::class)]
#[CoversClass(TenantMover::class)]
#[CoversClass(CopyPlan::class)]
#[CoversClass(MigrationRunner::class)]
#[CoversClass(MigrationTargets::class)]
#[CoversClass(MigrateTenantDatabasesCommand::class)]
#[CoversClass(CreateTenantDatabaseCommand::class)]
#[CoversClass(MoveTenantCommand::class)]
#[CoversClass(RefuseUnavailableTenant::class)]
#[Group('postgresql')]
final class PostgreSqlTenantDatabasesTest extends TenantDatabasesTestCase
{
    use TenantDatabasesTests;

    #[Override]
    protected static function variables(): array
    {
        return ['PHIL_TENANCY_DATABASE_TEST_POSTGRESQL_URL', 'PHIL_TEST_POSTGRESQL_URL'];
    }
}
