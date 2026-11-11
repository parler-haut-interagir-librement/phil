<?php

declare(strict_types=1);

namespace Phil\Sitemap;

/**
 * An image a page shows, by its address.
 */
final readonly class Image
{
    /** The address of the image, in its canonical form. */
    public string $loc;

    public function __construct(string $loc)
    {
        $this->loc = Location::check($loc, 'An image');
    }
}
