<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the tables of the error tracking to the schema the ORM generates for their connection: `doctrine:migrations:diff`
 * then writes the migration that creates them, and `doctrine:schema:validate` does not ask to drop them.
 */
final readonly class SchemaListener
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() === $this->connection) {
            $event->setSchema(ErrorTables::configure($event->getSchema()));
        }
    }
}
