<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * Where providers add the pages of a sitemap, each in a section.
 */
interface UrlContainer
{
    public function add(Url $url, string $section): void;
}
