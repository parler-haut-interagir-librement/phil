<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Merger;

use Override;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\Slug\Slug;

/**
 * Merges `RoutableCapable`: the source's slug, when it has one.
 */
final readonly class RoutableMerger implements Merger
{
    #[Override]
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if (!$target instanceof RoutableCapable || !$source instanceof RoutableCapable) {
            return;
        }

        $slug = $source->getSlug();

        if ($slug instanceof Slug) {
            $target->setSlug($slug);
        }
    }
}
