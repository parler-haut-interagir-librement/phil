<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the slug history table to the schema the ORM generates for the history's connection, the way the session, lock
 * and Messenger stores of Symfony add theirs: `doctrine:migrations:diff` then writes the migration that creates it,
 * and `doctrine:schema:validate` does not ask to drop it.
 */
final readonly class SlugHistorySchemaListener
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

        $event->setSchema(SlugHistoryTable::configure($event->getSchema()));
    }
}
