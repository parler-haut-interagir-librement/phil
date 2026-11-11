<?php

declare(strict_types=1);

namespace Phil\Migration;

use Doctrine\Migrations\Metadata\MigrationPlan;
use Doctrine\Migrations\Metadata\MigrationPlanList;
use Doctrine\Migrations\Migrator;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\ExecutionResult;
use Throwable;

/**
 * Decorates the migrator: once a run is over, the migrations that ran to the end are announced together, in one
 * release.
 *
 * A dry run announces nothing, nor does a failed run that was all or nothing; a failed run that was not announces the
 * migrations that ran before the failure, which stay.
 */
final readonly class AnnouncingMigrator implements Migrator
{
    public function __construct(
        private Migrator $migrator,
        private Announcements $announcements,
        private Announcer $announcer,
    ) {
    }

    public function migrate(MigrationPlanList $migrationsPlan, MigratorConfiguration $migratorConfiguration): array
    {
        $this->announcements->clear();

        try {
            $sql = $this->migrator->migrate($migrationsPlan, $migratorConfiguration);
        } catch (Throwable $throwable) {
            $this->announce($migrationsPlan, $migratorConfiguration, !$migratorConfiguration->isAllOrNothing());

            throw $throwable;
        }

        $this->announce($migrationsPlan, $migratorConfiguration, true);

        return $sql;
    }

    private function announce(MigrationPlanList $plan, MigratorConfiguration $configuration, bool $kept): void
    {
        $ran = [];

        foreach ($plan->getItems() as $item) {
            if ($this->ranToTheEnd($item)) {
                $ran[] = (string) $item->getVersion();
            }
        }

        $release = $this->announcements->release($kept && !$configuration->isDryRun() ? $ran : []);

        if (!$release->isEmpty()) {
            $this->announcer->announce($release);
        }
    }

    private function ranToTheEnd(MigrationPlan $item): bool
    {
        $result = $item->getResult();

        return $result instanceof ExecutionResult && !$result->isSkipped() && !$result->hasError();
    }
}
