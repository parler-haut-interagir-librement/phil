<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;

/**
 * Adds the tables of the bundle to the schema the ORM generates for its connection: `doctrine:migrations:diff` then
 * writes the migration that creates them, and `doctrine:schema:validate` does not ask to drop them.
 */
final readonly class PrivacySchemaListener
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema(PrivacyTables::configure($event->getSchema()));
    }
}
