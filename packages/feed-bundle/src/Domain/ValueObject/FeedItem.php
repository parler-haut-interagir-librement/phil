<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\FeedBundle\Domain\Exception\InvalidFeed;
use Phil\Url\Url;

/**
 * One entry of a feed: a news item, a page, an event, an episode.
 *
 * - `id` names the entry for good: a reader that sees it again knows it already has it, even when its address or its
 *   title change. It is an absolute IRI, as Atom asks: a `tag:` URI (`tag:example.org,2026:news-42`), a `urn:uuid:`,
 *   or the canonical address when the entry never moves;
 * - `url` is the page readers open, absolute;
 * - `published` is when it first went online; `updated` when it last changed, never before `published`: an earlier
 *   one is read as `published`;
 * - `summary` is plain text, `contentHtml` the whole entry as HTML, which the writers escape as each format asks;
 * - `language` is the entry's own, when it is not the feed's.
 */
final readonly class FeedItem
{
    public const int MAX_ID_LENGTH = 2048;

    public const int MAX_TITLE_LENGTH = 1024;

    public const int MAX_SUMMARY_BYTES = 65_536;

    public const int MAX_CONTENT_BYTES = 1_048_576;

    public const int MAX_AUTHORS = 32;

    public const int MAX_CATEGORIES = 64;

    public string $id;

    public string $title;

    public DateTimeImmutable $updated;

    public ?string $summary;

    public ?string $contentHtml;

    public ?string $language;

    /**
     * @param list<Author>   $authors    32 at most
     * @param list<Category> $categories 64 at most
     *
     * @throws InvalidFeed when a field breaks its rule
     */
    public function __construct(
        string $id,
        string $title,
        public Url $url,
        public DateTimeImmutable $published,
        ?DateTimeImmutable $updated = null,
        ?string $summary = null,
        ?string $contentHtml = null,
        public array $authors = [],
        public array $categories = [],
        public ?Attachment $attachment = null,
        ?string $language = null,
    ) {
        $this->id = Text::iri('entry id', $id, self::MAX_ID_LENGTH);
        $this->title = Text::line('entry title', $title, self::MAX_TITLE_LENGTH);
        $this->updated = $updated instanceof DateTimeImmutable ? max($published, $updated) : $published;
        $this->summary = Text::block('entry summary', $summary, self::MAX_SUMMARY_BYTES);
        $this->contentHtml = Text::block('entry content', $contentHtml, self::MAX_CONTENT_BYTES);
        $this->language = Text::language($language);

        if (\count($authors) > self::MAX_AUTHORS) {
            throw InvalidFeed::count('authors in an entry', \count($authors), self::MAX_AUTHORS);
        }

        if (\count($categories) > self::MAX_CATEGORIES) {
            throw InvalidFeed::count('categories in an entry', \count($categories), self::MAX_CATEGORIES);
        }
    }
}
