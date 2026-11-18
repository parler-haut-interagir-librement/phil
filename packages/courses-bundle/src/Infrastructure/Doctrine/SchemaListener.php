<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the tables of the courses to the schema the ORM generates for their connection: `doctrine:migrations:diff`
 * then writes the migration that creates them, and `doctrine:schema:validate` does not ask to drop them.
 */
final readonly class SchemaListener
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() === $this->connection) {
            $event->setSchema(CourseTables::configure($event->getSchema(), $this->connection->getDatabasePlatform()));
        }
    }
}
