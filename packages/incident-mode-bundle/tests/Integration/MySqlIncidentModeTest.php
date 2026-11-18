<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Integration;

use Override;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Infrastructure\DependencyInjection\IncidentModeServices;
use Phil\IncidentModeBundle\Infrastructure\Http\IncidentAdminController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The bundle on MySQL 8.4, at `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(IncidentModeServices::class)]
#[CoversClass(IncidentAdminController::class)]
#[CoversClass(Checkpoint::class)]
#[Group('mysql')]
final class MySqlIncidentModeTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
