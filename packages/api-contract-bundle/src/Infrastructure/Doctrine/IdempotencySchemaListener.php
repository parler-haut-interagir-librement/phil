<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Idempotency\Store\IdempotencyTable;

/**
 * Adds the table of idempotency keys to the schema the ORM generates for its connection, as Symfony's own stores
 * do: `doctrine:migrations:diff` writes the migration that creates it, `doctrine:schema:validate` keeps it.
 */
final readonly class IdempotencySchemaListener
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

        $event->setSchema(IdempotencyTable::configure($event->getSchema()));
    }
}
