<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

use Phil\SeoCheck\Exception\InvalidPage;
use Phil\SeoCheck\Markup\MarkupReader;
use Phil\Url\Url;

/**
 * What the rules check: the page as it was announced published — its canonical address, its language and whether
 * search engines may index it — and how it renders. The first rendering is the page at its own address; the others are
 * the same page reached by another path, `/` and `/fr/` for a home page, or rendered for another channel.
 *
 * An application that serves several storefronts or channels says, for each, whether the page is indexable there; a
 * channel it does not name follows the page.
 */
final readonly class CheckedPage
{
    /** @var non-empty-list<Rendering> */
    public array $renderings;

    /**
     * @param list<Rendering>     $renderings         the page at its own address first, then its other paths and channels
     * @param array<string, bool> $indexableByChannel whether the page is indexable in each channel the application names
     *
     * @throws InvalidPage when there is no rendering
     */
    public function __construct(
        public Url $canonicalUrl,
        public string $locale,
        public bool $indexable,
        array $renderings,
        public array $indexableByChannel = [],
    ) {
        if ([] === $renderings) {
            throw InvalidPage::noRendering();
        }

        $this->renderings = $renderings;
    }

    /**
     * The page built from its renderings, each read by `$reader`.
     *
     * @param list<RenderedPage>  $pages
     * @param array<string, bool> $indexableByChannel
     *
     * @throws InvalidPage when there is no rendering
     */
    public static function of(Url $canonicalUrl, string $locale, bool $indexable, array $pages, array $indexableByChannel = [], MarkupReader $reader = new MarkupReader()): self
    {
        return new self(
            $canonicalUrl,
            $locale,
            $indexable,
            array_map(static fn (RenderedPage $page): Rendering => new Rendering($page, $reader->read($page)), $pages),
            $indexableByChannel,
        );
    }

    /**
     * The page at its own address.
     */
    public function primary(): Rendering
    {
        return $this->renderings[0];
    }

    /**
     * Whether search engines may index the page in this channel: what the application says of the channel, else what
     * the page was published as.
     */
    public function isIndexableIn(?string $channel): bool
    {
        return null === $channel ? $this->indexable : $this->indexableByChannel[$channel] ?? $this->indexable;
    }
}
