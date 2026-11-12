<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Merger;

use Override;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Capability\LinkCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\ValueObject\Link;

/**
 * Merges `LinkCapable`: the source's link, whole, when it has one. A link is one target: a localized URL
 * never borrows the locale of a shared content link.
 */
final readonly class LinkMerger implements Merger
{
    #[Override]
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if (!$target instanceof LinkCapable || !$source instanceof LinkCapable) {
            return;
        }

        $link = $source->getLink();

        if ($link instanceof Link) {
            $target->setLink($link);
        }
    }
}
