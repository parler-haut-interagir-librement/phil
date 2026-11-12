<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Normalizer;

use Override;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;

/**
 * `SeoCapable` as plain data: `seoTitle`, `seoDescription`, `seoKeywords` (a list), `seoCanonicalUrl` (a
 * string), and the three directives `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap` as booleans — a directive
 * no row set reads as `false`, so a renderer never has to guess what `null` means.
 */
final readonly class SeoNormalizer implements Normalizer
{
    #[Override]
    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof SeoCapable) {
            return [];
        }

        return [
            'seoTitle' => $content->getSeoTitle(),
            'seoDescription' => $content->getSeoDescription(),
            'seoKeywords' => $content->getSeoKeywords(),
            'seoCanonicalUrl' => $content->getSeoCanonicalUrl()?->toString(),
            'seoNoIndex' => $content->getSeoNoIndex() ?? false,
            'seoNoFollow' => $content->getSeoNoFollow() ?? false,
            'seoHideInSitemap' => $content->getSeoHideInSitemap() ?? false,
        ];
    }
}
