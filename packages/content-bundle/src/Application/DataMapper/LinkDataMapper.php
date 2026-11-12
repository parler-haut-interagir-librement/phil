<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\DataMapper;

use Override;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Domain\Capability\LinkCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionLocales;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Writes `LinkCapable` on the localized row, from the `link` key: `null` clears the link, and an array names
 * its target, in the shape `LinkNormalizer` gives.
 *
 * - `{url: "https://…"}` for a URL;
 * - `{resourceKey: "article", resourceId: "…", locale: "fr"}` for a content root, the locale optional.
 *
 * An array that names both, or neither, is refused.
 */
final readonly class LinkDataMapper implements DataMapper
{
    #[Override]
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if (!$localized instanceof LinkCapable || !SubmittedData::has($data, 'link')) {
            return;
        }

        $link = SubmittedData::map($data, 'link');

        $localized->setLink(null === $link ? null : $this->link($link));
    }

    /**
     * @param array<array-key, mixed> $submitted
     *
     * @throws InvalidContent
     * @throws InvalidDimension when the pinned locale is malformed
     */
    private function link(array $submitted): Link
    {
        $url = SubmittedData::string($submitted, 'url');
        $resourceKey = SubmittedData::string($submitted, 'resourceKey');
        $resourceId = SubmittedData::string($submitted, 'resourceId');
        $locale = SubmittedData::string($submitted, 'locale');

        if (null !== $url) {
            if (null !== $resourceKey || null !== $resourceId || null !== $locale) {
                throw InvalidContent::because('A link points to a URL or to a content root, not both.');
            }

            return Link::toUrl($this->url($url));
        }

        if (null === $resourceKey || null === $resourceId) {
            throw InvalidContent::because('A link names a URL, or the resource key and the identifier of a content root.');
        }

        return Link::toContent($resourceKey, $resourceId, null === $locale ? null : DimensionLocales::parse($locale));
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
