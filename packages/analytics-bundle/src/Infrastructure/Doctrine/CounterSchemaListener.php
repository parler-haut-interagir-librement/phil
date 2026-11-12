<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the counter tables to the schema Doctrine compares with the database, so that migrations create them.
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

        $event->setSchema(CounterTables::configure($event->getSchema()));
    }
}
