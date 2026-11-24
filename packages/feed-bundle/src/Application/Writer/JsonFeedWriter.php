<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Writer;

use Override;
use Phil\FeedBundle\Application\Contract\FeedWriter;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Domain\ValueObject\Author;
use Phil\FeedBundle\Domain\ValueObject\Category;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;
use Phil\Url\Url;

/**
 * JSON Feed 1.1, written by `json_encode()`.
 *
 * - The feed has its title, its page of the site (`home_page_url`), its own address (`feed_url`), its description, its
 *   icon, its language, its authors, its hubs as `{"type": "WebSub", "url": …}` and, in a paged feed, `next_url`.
 * - An item has its identifier, its address, its title, its content as `content_html` — or, without one, its summary or
 *   else its title as `content_text`, since JSON Feed asks for one of the two —, its summary, its dates, its authors,
 *   its categories as `tags`, its language and its attachment.
 * - A field without a value is left out; dates are RFC 3339 in UTC.
 */
final readonly class JsonFeedWriter implements FeedWriter
{
    public const string VERSION = 'https://jsonfeed.org/version/1.1';

    private const int FLAGS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR;

    #[Override]
    public function format(): FeedFormat
    {
        return FeedFormat::Json;
    }

    #[Override]
    public function write(Feed $feed, FeedLinks $links): string
    {
        $channel = $feed->channel;
        $document = self::filled([
            'version' => self::VERSION,
            'title' => $channel->title,
            'home_page_url' => $channel->homePage->toString(),
            'feed_url' => $links->self->toString(),
            'description' => $channel->description,
            'icon' => $channel->icon?->toString(),
            'next_url' => $links->next?->toString(),
            'language' => $channel->language,
            'authors' => $this->authors($channel->authors),
            'hubs' => array_map(static fn (Url $hub): array => ['type' => 'WebSub', 'url' => $hub->toString()], $links->hubs),
        ]);
        $document['items'] = array_map($this->item(...), $feed->items);

        return json_encode($document, self::FLAGS)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function item(FeedItem $item): array
    {
        $attachment = $item->attachment;

        return self::filled([
            'id' => $item->id,
            'url' => $item->url->toString(),
            'title' => $item->title,
            'content_html' => $item->contentHtml,
            'content_text' => null === $item->contentHtml ? $item->summary ?? $item->title : null,
            'summary' => $item->summary,
            'date_published' => XmlText::rfc3339($item->published),
            'date_modified' => XmlText::rfc3339($item->updated),
            'authors' => $this->authors($item->authors),
            'tags' => array_map(static fn (Category $category): string => $category->display(), $item->categories),
            'language' => $item->language,
            'attachments' => $attachment instanceof Attachment ? [self::filled([
                'url' => $attachment->url->toString(),
                'mime_type' => $attachment->mediaType,
                'title' => $attachment->title,
                'size_in_bytes' => $attachment->sizeInBytes,
                'duration_in_seconds' => $attachment->durationInSeconds,
            ])] : [],
        ]);
    }

    /**
     * @param list<Author> $authors
     *
     * @return list<array<string, mixed>>
     */
    private function authors(array $authors): array
    {
        return array_map(static fn (Author $author): array => self::filled([
            'name' => $author->name,
            'url' => $author->url?->toString(),
            'avatar' => $author->avatar?->toString(),
        ]), $authors);
    }

    /**
     * The fields that have a value: neither `null` nor an empty list.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function filled(array $fields): array
    {
        return array_filter($fields, static fn (mixed $value): bool => null !== $value && [] !== $value);
    }
}
