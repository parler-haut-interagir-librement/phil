<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Markup;

/**
 * An `<a>` element of the page: its `href` as written, or null when it has none, and its text, its spaces collapsed.
 */
final readonly class Link
{
    public function __construct(
        public ?string $href,
        public string $text,
    ) {
    }
}
