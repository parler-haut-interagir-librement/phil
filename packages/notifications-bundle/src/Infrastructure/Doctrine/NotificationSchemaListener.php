<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the bundle's tables to the schema the ORM generates for their connection, as Symfony's own stores do:
 * `doctrine:migrations:diff` writes the migration that creates them, `doctrine:schema:validate` keeps them.
 */
final readonly class NotificationSchemaListener
{
    public function __construct(
        private Connection $connection,
        private bool $preferences = true,
        private bool $pushSubscriptions = false,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema(NotificationTables::configure($event->getSchema(), $this->preferences, $this->pushSubscriptions));
    }
}
