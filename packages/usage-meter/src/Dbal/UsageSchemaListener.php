<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the counter tables to the schema the ORM generates for the store's connection, as Symfony's own database
 * stores do: `doctrine:migrations:diff` writes the migration that creates them, `doctrine:schema:validate` keeps
 * them. Register it on the ORM's `postGenerateSchema` event.
 */
final readonly class UsageSchemaListener
{
    public function __construct(
        private Connection $connection,
        private DbalUsageStore $store,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema($this->store->configureSchema($event->getSchema()));
    }
}
