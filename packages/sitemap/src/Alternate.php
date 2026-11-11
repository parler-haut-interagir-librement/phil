<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * The same page in another language: its `hreflang` — "fr", "fr-CA", or "x-default" — and its address.
 */
final readonly class Alternate
{
    public string $hreflang;

    /** The address of the page in that language, in its canonical form. */
    public string $href;

    /**
     * @param bool $allowLocalhost whether `localhost` and the names under `.localhost` are addresses, in development
     *                             and tests
     */
    public function __construct(
        string $hreflang,
        string $href,
        bool $allowLocalhost = false,
    ) {
        $this->hreflang = str_replace('_', '-', $hreflang);

        if ('x-default' !== $this->hreflang && 1 !== preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,4})*$/D', $this->hreflang)) {
            throw InvalidSitemap::because(\sprintf('A hreflang is a language, maybe followed by a script or a region — "fr", "fr-CA" —, or "x-default"; "%s" is not.', $hreflang));
        }

        $this->href = Location::check($href, 'An alternate', $allowLocalhost);
    }
}
