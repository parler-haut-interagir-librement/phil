<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\ClassroomTables;

/**
 * Adds the tables of the rooms to the schema the ORM generates for its connection: `doctrine:migrations:diff` writes
 * the migration that creates them, `doctrine:schema:validate` keeps them.
 */
final readonly class ClassroomSchemaListener
{
    /**
     * @param non-empty-string $prefix
     */
    public function __construct(
        private Connection $connection,
        private string $prefix = ClassroomTables::PREFIX,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema(ClassroomTables::for($this->connection->getDatabasePlatform(), $this->prefix)->configure($event->getSchema()));
    }
}
