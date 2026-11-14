<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the stockroom's tables to the schema the ORM generates for its connection, as Symfony's own database stores do:
 * `doctrine:migrations:diff` writes the migration that creates them, `doctrine:schema:validate` keeps them. Registered
 * on the ORM's `postGenerateSchema` event when the ORM is installed.
 */
final readonly class StockroomSchemaListener
{
    public function __construct(
        private Connection $connection,
        private StockroomTables $tables,
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
