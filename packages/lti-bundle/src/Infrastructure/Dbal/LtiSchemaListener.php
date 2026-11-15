<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the bundle's tables to the schema the ORM generates for their connection: `doctrine:migrations:diff` writes the
 * migration that creates them. Registered on the ORM's `postGenerateSchema` event when the ORM is installed.
 */
final readonly class LtiSchemaListener
{
    public function __construct(
        private Connection $connection,
        private LtiTables $tables,
    ) {
    }

    /**
     * @throws Exception when the platform of the connection cannot be told
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema($this->tables->configureSchema($event->getSchema(), $this->connection->getDatabasePlatform()));
    }
}
