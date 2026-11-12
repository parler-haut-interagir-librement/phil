<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\LocaleDimensions\Locale;
use Phil\Url\Url;

/**
 * The implementation of `LinkCapable`, without any persistence mapping.
 *
 * The link is held in four properties, one column each: the resource key, the identifier and the locale of a
 * content target, or the URL. A query can then find every row that points to one root. `setLink()` writes
 * the four together, so they never describe two targets at once.
 *
 * @phpstan-require-implements LinkCapable
 */
trait LinkTrait
{
    private ?string $linkResourceKey = null;

    private ?string $linkResourceId = null;

    private ?Locale $linkLocale = null;

    private ?string $linkUrl = null;

    public function getLink(): ?Link
    {
        if (null !== $this->linkUrl) {
            return Link::toUrl(Url::fromString($this->linkUrl));
        }

        if (null === $this->linkResourceKey) {
            return null;
        }

        return Link::toContent($this->linkResourceKey, $this->linkResourceId ?? '', $this->linkLocale);
    }

    public function setLink(?Link $link): void
    {
        $this->linkResourceKey = $link?->resourceKey;
        $this->linkResourceId = $link?->resourceId;
        $this->linkLocale = $link?->locale;
        $this->linkUrl = $link?->url?->toString();
    }
}
