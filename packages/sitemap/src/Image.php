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

    /**
     * @param bool $allowLocalhost whether `localhost` and the names under `.localhost` are addresses, in development
     *                             and tests
     */
    public function __construct(string $loc, bool $allowLocalhost = false)
    {
        $this->loc = Location::check($loc, 'An image', $allowLocalhost);
    }
}
