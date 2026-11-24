<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Markup;

/**
 * A `<link rel="alternate" hreflang="…">` of the page: the language it names and its `href`, as written.
 */
final readonly class Alternate
{
    public function __construct(
        public string $hreflang,
        public string $href,
    ) {
    }
}
