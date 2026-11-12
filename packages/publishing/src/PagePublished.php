<?php

declare(strict_types=1);

namespace Phil\Publishing;

use Phil\Publishing\Contract\PageEvent;
use Phil\Publishing\Exception\InvalidPageEvent;
use Phil\Url\Url;

/**
 * A page went online, or went online again with new content: readers see it at its canonical address.
 *
 * ```php
 * $dispatcher->dispatch(new PagePublished(Url::fromString('https://example.org/fr/news/opening'), 'fr', true, 'news:42'));
 * ```
 */
final readonly class PagePublished implements PageEvent
{
    public string $locale;

    public string $ownerId;

    /**
     * @throws InvalidPageEvent when the language is not a BCP 47 tag, or the owner identifier is malformed
     */
    public function __construct(
        public Url $canonicalUrl,
        string $locale,
        public bool $indexable,
        string $ownerId,
    ) {
        $this->locale = PageEventFields::locale($locale);
        $this->ownerId = PageEventFields::ownerId($ownerId);
    }
}
