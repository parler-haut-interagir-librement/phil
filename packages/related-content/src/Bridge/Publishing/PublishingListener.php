<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Bridge\Publishing;

use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\RelatedContent\Recomputation;
use Phil\RelatedContent\RelatedContent;

/**
 * Computes the neighbours when phil/publishing says a page went online or offline; the owner identifier of the event,
 * such as `article:42`, is the item identifier. Register it as a listener of both events.
 */
final readonly class PublishingListener
{
    public function __construct(
        private RelatedContent $related,
    ) {
    }

    public function onPagePublished(PagePublished $event): Recomputation
    {
        return $this->related->published($event->ownerId);
    }

    public function onPageRetired(PageRetired $event): Recomputation
    {
        return $this->related->retired($event->ownerId);
    }
}
