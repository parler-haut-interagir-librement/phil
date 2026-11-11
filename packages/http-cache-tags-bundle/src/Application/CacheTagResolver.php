<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Application;

/**
 * The tags of the responses that show an entity: when it changes, they are purged. The application writes one per
 * kind of entity; the bundle finds them by autoconfiguration.
 */
interface CacheTagResolver
{
    /**
     * @return iterable<string> nothing when this resolver does not know the entity
     */
    public function tagsFor(object $entity): iterable;
}
