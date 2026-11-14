<?php

declare(strict_types=1);

/*
 * A console with the migration command alone, for the tests that stop it: `stoppable-console.php <log> <tenants> …`,
 * the tenants as `reference=database` separated by commas, then the arguments of the console. The migrations are those
 * of the runner's fixture, the suspensions are kept in memory, and the grace period is two seconds.
 *
 * With PHIL_TDB_FATAL set, the first database that migrates successfully ends the console with a fatal error.
 */

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrateTenantDatabasesCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrationTargets;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\ConfiguredPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\MemoryAvailability;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

$root = dirname(__DIR__, 5);
require (is_file($root.'/vendor/autoload.php') ? $root : dirname(__DIR__, 3)).'/vendor/autoload.php';

$arguments = is_array($_SERVER['argv'] ?? null) ? array_values(array_filter($_SERVER['argv'], is_string(...))) : [];
[$script, $log, $tenants] = [$arguments[0] ?? '', $arguments[1] ?? '', $arguments[2] ?? ''];
$dedicated = [];

foreach (array_filter(explode(',', $tenants), static fn (string $pair): bool => '' !== $pair) as $pair) {
    [$reference, $database] = explode('=', $pair, 2) + [1 => ''];
    $dedicated[$reference] = $database;
}

$availability = new readonly class(new MemoryAvailability(), false !== getenv('PHIL_TDB_FATAL')) implements TenantDatabaseAvailability {
    public function __construct(
        private MemoryAvailability $memory,
        private bool $fatal,
    ) {
    }

    public function suspendDatabase(Placement $database): void
    {
        $this->memory->suspendDatabase($database);
    }

    public function resumeDatabase(Placement $database): void
    {
        if ($this->fatal) {
            ini_set('memory_limit', '32M');
            $exhausted = str_repeat('x', 64 * 1024 * 1024);
            echo strlen($exhausted);
        }

        $this->memory->resumeDatabase($database);
    }

    public function isDatabaseSuspended(Placement $database): bool
    {
        return $this->memory->isDatabaseSuspended($database);
    }

    public function suspendTenant(TenantId $tenant): void
    {
        $this->memory->suspendTenant($tenant);
    }

    public function resumeTenant(TenantId $tenant): void
    {
        $this->memory->resumeTenant($tenant);
    }

    public function isTenantSuspended(TenantId $tenant): bool
    {
        return $this->memory->isTenantSuspended($tenant);
    }
};

$runner = new MigrationRunner([dirname(__DIR__, 2).'/Unit/Fixture/migrate.php', $log], $availability, 4, 60.0, \PHP_BINARY, 2.0);
$application = new Application();
$application->addCommand(new MigrateTenantDatabasesCommand(new MigrationTargets(new ConfiguredPlacements($dedicated)), $runner));
$application->setAutoExit(true);

$application->run(new ArgvInput([$script, ...array_slice($arguments, 3)]));
