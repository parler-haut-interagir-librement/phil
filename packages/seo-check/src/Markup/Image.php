<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Markup;

/**
 * An `<img>` element of the page: its `src` and its `alt` as written, null when absent, and whether it is hidden from
 * readers by `aria-hidden="true"` or a role of `presentation` or `none`.
 */
final readonly class Image
{
    public function __construct(
        public ?string $src,
        public ?string $alt,
        public bool $hidden = false,
    ) {
    }
}
