<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;

/**
 * Adds the tables of the bundle to the schema the ORM generates for its connection: `doctrine:migrations:diff` then
 * writes the migration that creates them, and `doctrine:schema:validate` does not ask to drop them. Only the tables it
 * is given are added, those of the features the application uses with the table of the bundle as their store.
 */
final readonly class PrivacySchemaListener
{
    /**
     * @param list<string> $tables among PrivacyTables::ALL
     */
    public function __construct(
        private Connection $connection,
        private array $tables = PrivacyTables::ALL,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema(PrivacyTables::configure($event->getSchema(), $this->connection->getDatabasePlatform(), $this->tables));
    }
}
