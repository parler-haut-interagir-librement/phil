<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the bundle's tables to the schema the ORM generates for their connection: `doctrine:migrations:diff` writes
 * the migration that creates them, `doctrine:schema:validate` keeps them.
 */
final readonly class SurveySchemaListener
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

        $event->setSchema(SurveyTables::configure($event->getSchema(), $this->connection->getDatabasePlatform()));
    }
}
