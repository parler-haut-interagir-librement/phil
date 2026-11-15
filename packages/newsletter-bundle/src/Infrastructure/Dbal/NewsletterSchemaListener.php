<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the newsletter's tables to the schema the ORM generates for the bundle's connection: `doctrine:migrations:diff`
 * writes the migration that creates them.
 */
final readonly class NewsletterSchemaListener
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

        $event->setSchema(NewsletterSchema::configure($event->getSchema()));
    }
}
