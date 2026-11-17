<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional\App;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\Routing\RouteId;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\LocaleType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\RouteIdType;

/**
 * Another publication taking a slug between the check and the write: when a flush is about to insert the route of
 * `$slug`, a route of another content takes it first, on the same connection, as if another request had committed it
 * after the check found the slug free.
 */
final class SlugTakenMeanwhile
{
    public ?string $slug = null;

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();

        foreach ($entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if (null !== $this->slug && $entity instanceof Route && $entity->slug()->value === $this->slug) {
                $entityManager->getConnection()->insert('phil_content_route', [
                    'id' => RouteId::generate(),
                    'resource_key' => $entity->content()->resourceKey,
                    'resource_id' => 'another',
                    'slug' => $this->slug,
                    'history' => false,
                    'locale' => $entity->locale(),
                ], ['id' => RouteIdType::NAME, 'history' => Types::BOOLEAN, 'locale' => LocaleType::NAME]);
                $this->slug = null;
            }
        }
    }
}
