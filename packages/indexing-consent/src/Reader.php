<?php

declare(strict_types=1);

namespace Phil\IndexingConsent;

/**
 * What reads a public record beyond its own page, and asks whether it may.
 */
enum Reader: string
{
    /** Search engines, told by the robots directives of the page. */
    case SearchEngines = 'search_engines';

    /** The sitemap, which lists the pages a search engine should find. */
    case Sitemap = 'sitemap';

    /** The structured data of a page, which describes its subject to search engines and other machines. */
    case StructuredData = 'structured_data';

    /** The site's own listings: its directory, its search, its pages of records. */
    case SiteListings = 'site_listings';
}
