<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the trail's table to the schema Doctrine compares with the database, so that migrations create it.
 */
final readonly class AuditSchemaListener
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

        $event->setSchema(AuditTable::configure($event->getSchema(), $this->connection->getDatabasePlatform()));
    }
}
