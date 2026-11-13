<?php

declare(strict_types=1);

namespace Phil\IncidentMode\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the table of the activations to the schema the ORM generates for the store's connection: `doctrine:migrations:diff`
 * writes the migration that creates it, `doctrine:schema:validate` keeps it. Register it on the ORM's
 * `postGenerateSchema` event.
 */
final readonly class IncidentSchemaListener
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

        $event->setSchema(IncidentTables::configure($event->getSchema()));
    }
}
