<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the bundle's table to the schema the ORM generates for its connection, as Symfony's own stores do:
 * `doctrine:migrations:diff` writes the migration that creates it, `doctrine:schema:validate` keeps it. The tables are
 * built for the platform of the connection: on MySQL and MariaDB, their keys in `utf8mb4_bin`.
 */
final readonly class SettingSchemaListener
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

        $event->setSchema(SettingTables::configure($event->getSchema(), $this->connection->getDatabasePlatform()));
    }
}
