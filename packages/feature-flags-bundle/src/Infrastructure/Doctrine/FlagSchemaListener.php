<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the flag tables to the schema the ORM generates for the store's connection, the way the session, lock and
 * Messenger stores of Symfony add theirs: `doctrine:migrations:diff` then writes the migration that creates them,
 * and `doctrine:schema:validate` does not ask to drop them.
 */
final readonly class FlagSchemaListener
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

        $event->setSchema(FlagTables::configure($event->getSchema()));
    }
}
