<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * An image a page shows, by its address.
 */
final readonly class Image
{
    public function __construct(
        public string $loc,
    ) {
        Location::check($loc, 'An image');
    }
}
