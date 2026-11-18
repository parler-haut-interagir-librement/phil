<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the counter tables to the schema Doctrine compares with the database, so that migrations create them, built
 * for the platform of the connection: on MySQL and MariaDB, their keys in `utf8mb4_bin`.
 */
final readonly class CounterSchemaListener
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

        $event->setSchema(CounterTables::configure($event->getSchema(), $this->connection->getDatabasePlatform()));
    }
}
