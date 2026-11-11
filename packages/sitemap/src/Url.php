<?php

declare(strict_types=1);

namespace Phil\Sitemap;

use DateTimeImmutable;

/**
 * A page of a sitemap: its address, and what the protocol lets a site say of it — when it last changed, how often it
 * changes, how it ranks among the site's pages —, the same page in other languages, and the images it shows.
 */
final readonly class Url
{
    public const int MAX_IMAGES = 1000;

    /** The address of the page, in its canonical form. */
    public string $loc;

    /**
     * @param list<Alternate> $alternates
     * @param list<Image>     $images
     * @param bool            $allowLocalhost whether `localhost` and the names under `.localhost` are addresses, in
     *                                        development and tests
     */
    public function __construct(
        string $loc,
        public ?DateTimeImmutable $lastmod = null,
        public ?ChangeFrequency $changefreq = null,
        public ?float $priority = null,
        public array $alternates = [],
        public array $images = [],
        bool $allowLocalhost = false,
    ) {
        $this->loc = Location::check($loc, 'A page', $allowLocalhost);

        if (null !== $priority && ($priority < 0.0 || $priority > 1.0 || round($priority, 1) !== $priority)) {
            throw InvalidSitemap::because(\sprintf('A priority is 0.0 to 1.0, in steps of 0.1; %s is not.', $priority));
        }

        if (\count($images) > self::MAX_IMAGES) {
            throw InvalidSitemap::because(\sprintf('A page lists %d images at most; %d is too many.', self::MAX_IMAGES, \count($images)));
        }
    }
}
