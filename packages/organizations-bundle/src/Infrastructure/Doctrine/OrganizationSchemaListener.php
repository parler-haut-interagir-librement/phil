<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\OrganizationsBundle\Infrastructure\Dbal\OrganizationTables;

/**
 * Adds the tables of the organizations to the schema the ORM generates for their connection: `doctrine:migrations:diff`
 * writes the migration that creates them, `doctrine:schema:validate` keeps them. They are built for the platform of
 * the connection, so that every identifier and key column compares byte for byte.
 */
final readonly class OrganizationSchemaListener
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

        $event->setSchema(OrganizationTables::for($this->connection->getDatabasePlatform())->configure($event->getSchema()));
    }
}
