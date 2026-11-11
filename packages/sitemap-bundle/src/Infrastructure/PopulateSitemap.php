<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Infrastructure;

use Phil\Sitemap\UrlContainer;

/**
 * Dispatched as a sitemap is built, after the providers: a listener adds pages to the container, maybe only those of
 * the section asked for.
 */
final readonly class PopulateSitemap
{
    /**
     * @param ?string $section the section asked for, or null for all of them
     */
    public function __construct(
        public UrlContainer $urls,
        public ?string $section,
    ) {
    }
}
