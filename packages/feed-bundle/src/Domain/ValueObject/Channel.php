<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use Phil\FeedBundle\Domain\Exception\InvalidFeed;
use Phil\Url\Url;

/**
 * What a feed says of itself: its title, the page of the site it follows, a description, its language, its authors
 * and an icon.
 *
 * A source gives it without reading its entries: the links of a page (`phil_feed_links()`) name each feed by its title,
 * on every page the site renders.
 */
final readonly class Channel
{
    public const int MAX_TITLE_LENGTH = 255;

    public const int MAX_DESCRIPTION_BYTES = 4096;

    public string $title;

    public ?string $description;

    public ?string $language;

    /**
     * @param list<Author> $authors 32 at most
     *
     * @throws InvalidFeed when a field breaks its rule
     */
    public function __construct(
        string $title,
        public Url $homePage,
        ?string $description = null,
        ?string $language = null,
        public array $authors = [],
        public ?Url $icon = null,
    ) {
        $this->title = Text::line('feed title', $title, self::MAX_TITLE_LENGTH);
        $this->description = Text::block('feed description', $description, self::MAX_DESCRIPTION_BYTES);
        $this->language = Text::language($language);

        if (\count($authors) > FeedItem::MAX_AUTHORS) {
            throw InvalidFeed::count('authors in a feed', \count($authors), FeedItem::MAX_AUTHORS);
        }
    }
}
