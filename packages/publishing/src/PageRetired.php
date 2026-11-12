<?php

declare(strict_types=1);

namespace Phil\Publishing;

use Phil\Publishing\Contract\PageEvent;
use Phil\Publishing\Exception\InvalidPageEvent;
use Phil\Url\Url;

/**
 * A page went offline — taken offline, or deleted —: readers no longer see it at its canonical address. `indexable`
 * says whether it could be indexed while it was online, so a listener that told search engines about it knows to tell
 * them again.
 *
 * ```php
 * $dispatcher->dispatch(new PageRetired(Url::fromString('https://example.org/fr/news/opening'), 'fr', true, 'news:42'));
 * ```
 */
final readonly class PageRetired implements PageEvent
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
