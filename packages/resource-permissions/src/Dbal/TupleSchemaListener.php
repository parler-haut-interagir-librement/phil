<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the tuple table to the schema the ORM generates for the store's connection: `doctrine:migrations:diff` writes
 * the migration that creates it, `doctrine:schema:validate` keeps it. Register it on the ORM's `postGenerateSchema`
 * event.
 */
final readonly class TupleSchemaListener
{
    public function __construct(
        private Connection $connection,
        private DbalTupleStore $store,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema($this->store->configureSchema($event->getSchema()));
    }
}
