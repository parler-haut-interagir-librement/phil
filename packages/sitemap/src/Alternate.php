<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * The same page in another language: its `hreflang` — "fr", "fr-CA", or "x-default" — and its address.
 */
final readonly class Alternate
{
    public string $hreflang;

    public function __construct(
        string $hreflang,
        public string $href,
    ) {
        $this->hreflang = str_replace('_', '-', $hreflang);

        if ('x-default' !== $this->hreflang && 1 !== preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,4})*$/D', $this->hreflang)) {
            throw InvalidSitemap::because(\sprintf('A hreflang is a language, maybe followed by a script or a region — "fr", "fr-CA" —, or "x-default"; "%s" is not.', $hreflang));
        }

        Location::check($href, 'An alternate');
    }
}
