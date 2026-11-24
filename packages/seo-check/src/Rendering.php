<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

use Phil\SeoCheck\Markup\PageMarkup;

/**
 * A rendered page and what was read of its HTML.
 */
final readonly class Rendering
{
    public function __construct(
        public RenderedPage $page,
        public PageMarkup $markup,
    ) {
    }
}
