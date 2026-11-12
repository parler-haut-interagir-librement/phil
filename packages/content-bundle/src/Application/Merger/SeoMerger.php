<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Merger;

use Override;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\Url\Url;

/**
 * Merges `SeoCapable`, value by value: each the source sets replaces the target's. A text or a URL is set
 * when it is not `null`, the keywords when the list is not empty, a directive when it is `true` or `false`
 * — so a localized `false` lifts a shared `true`.
 */
final readonly class SeoMerger implements Merger
{
    #[Override]
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if (!$target instanceof SeoCapable || !$source instanceof SeoCapable) {
            return;
        }

        $title = $source->getSeoTitle();

        if (null !== $title) {
            $target->setSeoTitle($title);
        }

        $description = $source->getSeoDescription();

        if (null !== $description) {
            $target->setSeoDescription($description);
        }

        $keywords = $source->getSeoKeywords();

        if ([] !== $keywords) {
            $target->setSeoKeywords($keywords);
        }

        $canonicalUrl = $source->getSeoCanonicalUrl();

        if ($canonicalUrl instanceof Url) {
            $target->setSeoCanonicalUrl($canonicalUrl);
        }

        $noIndex = $source->getSeoNoIndex();

        if (null !== $noIndex) {
            $target->setSeoNoIndex($noIndex);
        }

        $noFollow = $source->getSeoNoFollow();

        if (null !== $noFollow) {
            $target->setSeoNoFollow($noFollow);
        }

        $hideInSitemap = $source->getSeoHideInSitemap();

        if (null !== $hideInSitemap) {
            $target->setSeoHideInSitemap($hideInSitemap);
        }
    }
}
