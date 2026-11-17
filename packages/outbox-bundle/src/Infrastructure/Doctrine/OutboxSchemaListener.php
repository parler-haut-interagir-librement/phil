<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the outbox table to the schema the ORM generates for its connection, as Symfony's own database stores do:
 * `doctrine:migrations:diff` writes the migration that creates it, `doctrine:schema:validate` keeps it. Registered on
 * the ORM's `postGenerateSchema` event when the ORM is installed.
 */
final readonly class OutboxSchemaListener
{
    public function __construct(
        private Connection $connection,
        private OutboxTable $table,
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

        $event->setSchema($this->table->configureSchema($event->getSchema(), $this->connection->getDatabasePlatform()));
    }
}
