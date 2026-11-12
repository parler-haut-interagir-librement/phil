<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\DataMapper;

use Override;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Writes `SeoCapable` on the localized row: what a search engine reads is written per language.
 *
 * Keys, each optional: `seoTitle`, `seoDescription`, `seoKeywords` (a list, or one comma-separated string),
 * `seoCanonicalUrl`, `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap`. An absent key leaves its value alone;
 * `null` clears it.
 */
final readonly class SeoDataMapper implements DataMapper
{
    #[Override]
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if (!$localized instanceof SeoCapable) {
            return;
        }

        if (SubmittedData::has($data, 'seoTitle')) {
            $localized->setSeoTitle(SubmittedData::string($data, 'seoTitle'));
        }

        if (SubmittedData::has($data, 'seoDescription')) {
            $localized->setSeoDescription(SubmittedData::string($data, 'seoDescription'));
        }

        if (SubmittedData::has($data, 'seoKeywords')) {
            $localized->setSeoKeywords(SubmittedData::stringList($data, 'seoKeywords'));
        }

        if (SubmittedData::has($data, 'seoCanonicalUrl')) {
            $url = SubmittedData::string($data, 'seoCanonicalUrl');
            $localized->setSeoCanonicalUrl(null === $url ? null : $this->url($url));
        }

        if (SubmittedData::has($data, 'seoNoIndex')) {
            $localized->setSeoNoIndex(SubmittedData::bool($data, 'seoNoIndex'));
        }

        if (SubmittedData::has($data, 'seoNoFollow')) {
            $localized->setSeoNoFollow(SubmittedData::bool($data, 'seoNoFollow'));
        }

        if (SubmittedData::has($data, 'seoHideInSitemap')) {
            $localized->setSeoHideInSitemap(SubmittedData::bool($data, 'seoHideInSitemap'));
        }
    }

    /**
     * @throws InvalidContent
     */
    private function url(string $url): Url
    {
        try {
            return Url::fromString($url);
        } catch (UrlException $urlException) {
            throw InvalidContent::because($urlException->getMessage());
        }
    }
}
