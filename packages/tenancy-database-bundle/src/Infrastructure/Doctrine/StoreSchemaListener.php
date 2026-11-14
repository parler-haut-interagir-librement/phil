<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the tables of the durable stores the bundle uses to the schema Doctrine compares with the database, so that
 * `doctrine:migrations:diff` and `doctrine:schema:update` create them.
 *
 * The migrations of every database are the same, so the tables reach the dedicated databases too, where they stay
 * empty: the stores read and write them in the shared database only.
 */
final readonly class StoreSchemaListener
{
    public function __construct(
        private Connection $connection,
        private bool $placements,
        private bool $suspensions,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema(StoreTables::configure($event->getSchema(), $this->placements, $this->suspensions));
    }
}
