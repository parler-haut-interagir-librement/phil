<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application;

use DateTimeImmutable;

/**
 * One published issue as a feed entry reads it: its identifier, its title, its absolute address, its summary — the
 * preheader —, its web version, when it was sent and its tags.
 */
final readonly class FeedItem
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public string $summary,
        public string $html,
        public DateTimeImmutable $sentAt,
        public array $tags,
    ) {
    }
}
