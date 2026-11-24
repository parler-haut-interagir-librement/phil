<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures;

use DateTimeImmutable;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Domain\ValueObject\Author;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\Url\Url;

/**
 * Single entries and small feeds for the cases one writer test looks at.
 */
final class Entries
{
    /**
     * @param list<Author> $authors
     */
    public static function item(string $title = 'Entry', ?string $summary = null, ?string $content = null, array $authors = [], ?Attachment $attachment = null, string $id = 'urn:uuid:0190f3a2-0000-7000-8000-000000000001'): FeedItem
    {
        return new FeedItem($id, $title, Url::fromString('https://example.org/entry'), new DateTimeImmutable('2026-11-21T08:30:00Z'), null, $summary, $content, $authors, [], $attachment);
    }

    public static function feed(FeedItem ...$items): Feed
    {
        return new Feed(new Channel('Feed', Url::fromString('https://example.org/')), array_values($items));
    }

    /**
     * @param list<Author> $authors
     */
    public static function feedBy(array $authors, FeedItem ...$items): Feed
    {
        return new Feed(new Channel('Feed', Url::fromString('https://example.org/'), authors: $authors), array_values($items));
    }
}
