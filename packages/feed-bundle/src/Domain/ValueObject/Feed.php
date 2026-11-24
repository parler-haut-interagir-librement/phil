<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\FeedBundle\Domain\Exception\InvalidFeed;

/**
 * A feed as the writers read it: its channel, the entries of one page, the most recent first, which page it is and
 * whether an older one follows, and when it last changed — the latest `updated` of its entries, or the Unix epoch when
 * it has none.
 */
final readonly class Feed
{
    public const int MAX_ITEMS = FeedQuery::MAX_LIMIT;

    public DateTimeImmutable $updated;

    /**
     * @param list<FeedItem> $items 500 at most
     *
     * @throws InvalidFeed when the entries are more than 500
     */
    public function __construct(
        public Channel $channel,
        public array $items,
        public int $page = 1,
        public bool $hasNextPage = false,
    ) {
        if (\count($items) > self::MAX_ITEMS) {
            throw InvalidFeed::count('entries', \count($items), self::MAX_ITEMS);
        }

        $updated = new DateTimeImmutable('@0');

        foreach ($items as $item) {
            $updated = max($updated, $item->updated);
        }

        $this->updated = $updated;
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * A hash of what the feed shows: its channel, its page, and the identifier and last change of each entry. Two
     * feeds with the same fingerprint are written alike, so an HTTP cache may keep one for the other (`ETag`).
     */
    public function fingerprint(): string
    {
        $channel = $this->channel;
        $parts = [$channel->title, $channel->homePage->toString(), $channel->description ?? '', $channel->language ?? '', $channel->icon?->toString() ?? '', \count($channel->authors), $this->page, $this->hasNextPage ? 'next' : 'last'];

        foreach ($this->items as $item) {
            $parts[] = $item->id;
            $parts[] = $item->updated->format('U.u');
        }

        return hash('xxh128', implode("\0", $parts));
    }
}
