<?php

declare(strict_types=1);

namespace Phil\Migration;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;

/**
 * Decorates the factory of the migrations: every `Announcing` migration it makes gets the announcements of the run.
 */
final readonly class AnnouncingMigrationFactory implements MigrationFactory
{
    public function __construct(
        private MigrationFactory $factory,
        private Announcements $announcements,
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $migration = $this->factory->createVersion($migrationClassName);

        if ($migration instanceof Announcing) {
            $migration->announceWith($this->announcements);
        }

        return $migration;
    }
}
