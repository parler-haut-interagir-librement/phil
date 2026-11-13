<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the check-in table to the schema the ORM generates for the ledger's connection: `doctrine:migrations:diff`
 * writes the migration that creates it, `doctrine:schema:validate` keeps it. Registered on the ORM's
 * `postGenerateSchema` event when the ORM is installed.
 */
final readonly class CheckInSchemaListener
{
    public function __construct(
        private Connection $connection,
        private DoctrineCheckInLedger $ledger,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema($this->ledger->configureSchema($event->getSchema()));
    }
}
