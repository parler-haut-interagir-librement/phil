<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\AccessLinks\Dbal\DbalInvitationStore;

/**
 * Adds the tables of the links, those of `phil/access-links`, to the schema the ORM generates, so that
 * `doctrine:migrations:diff` and the schema tool write them beside the tables of the polls. A schema that has them
 * already is left as it is.
 */
final readonly class InvitationSchemaListener
{
    public function __construct(
        private DbalInvitationStore $store,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $event->setSchema($this->store->configureSchema($event->getSchema()));
    }
}
