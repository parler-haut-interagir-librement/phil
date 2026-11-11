<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Functional\App;

use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * A listener of the application that tags a cacheable response late, once the controller has answered.
 */
final readonly class LateTags
{
    public function __construct(
        private ResponseTags $tags,
    ) {
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($event->getRequest()->isMethodCacheable()) {
            $this->tags->add('late');
        }
    }
}
