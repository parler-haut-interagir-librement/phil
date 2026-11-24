<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the archive's tables to the schema the ORM generates for the archive's connection: `doctrine:migrations:diff`
 * writes the migration that creates them.
 */
final readonly class ArchiveSchemaListener
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();

        if ($connection !== $this->connection) {
            return;
        }

        $event->setSchema(ArchiveSchema::configure($event->getSchema(), $connection->getDatabasePlatform()));
    }
}
