<?php

declare(strict_types=1);

namespace Phil\Publishing\Contract;

use Phil\Url\Url;

/**
 * What a listener reads of a page that goes online or offline: where readers find it, in which language, whether
 * search engines may index it, and how the package that owns it names it.
 *
 * The package that owns a page dispatches the event once the change is stored, never before: a listener — the sitemap,
 * an HTTP cache, a search engine ping, a feed hub — acts on a page that readers can already see, or no longer see.
 */
interface PageEvent
{
    /**
     * The address readers find the page at, absolute and normalised.
     */
    public Url $canonicalUrl { get; }

    /**
     * The language of the page, a BCP 47 tag: `fr`, `fr-CA`, `zh-Hant-TW`.
     */
    public string $locale { get; }

    /**
     * Whether search engines may index the page; a page marked `noindex` is not.
     */
    public bool $indexable { get; }

    /**
     * The identifier the owning package gives the page, as `news:0190f3a2-…`: the same for every event of that page,
     * and unique among the pages of the application.
     */
    public string $ownerId { get; }
}
