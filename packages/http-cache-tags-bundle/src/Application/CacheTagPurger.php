<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Application;

use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag;

/**
 * Tells the cache in front of the application to forget the responses that carry these tags.
 */
interface CacheTagPurger
{
    /**
     * @param non-empty-list<CacheTag> $tags
     */
    public function purge(array $tags): void;
}
