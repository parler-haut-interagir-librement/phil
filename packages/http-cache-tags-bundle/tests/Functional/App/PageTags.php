<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Functional\App;

use Phil\HttpCacheTagsBundle\Application\CacheTagResolver;
use Phil\HttpCacheTagsBundle\Tests\Functional\App\Entity\Page;

final class PageTags implements CacheTagResolver
{
    public function tagsFor(object $entity): iterable
    {
        return $entity instanceof Page ? ['page:'.$entity->id, 'page-list'] : [];
    }
}
