<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Merger;

use Override;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * Merges `ExcerptCapable`: the source's title, description and image, each only when the source sets it, so
 * a localized excerpt keeps the image of the shared row.
 */
final readonly class ExcerptMerger implements Merger
{
    #[Override]
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if (!$target instanceof ExcerptCapable || !$source instanceof ExcerptCapable) {
            return;
        }

        $title = $source->getExcerptTitle();

        if (null !== $title) {
            $target->setExcerptTitle($title);
        }

        $description = $source->getExcerptDescription();

        if (null !== $description) {
            $target->setExcerptDescription($description);
        }

        $image = $source->getExcerptImage();

        if (null !== $image) {
            $target->setExcerptImage($image);
        }
    }
}
