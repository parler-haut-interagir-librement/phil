<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * Adds pages to a sitemap. Asked for one section, a provider may add only that one's pages, so as not to load the
 * others; the container keeps only them anyway.
 */
interface UrlProvider
{
    /**
     * @param ?string $section the section asked for, or null for all of them
     */
    public function provide(UrlContainer $urls, ?string $section): void;
}
