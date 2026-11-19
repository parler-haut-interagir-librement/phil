<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds;

/**
 * Asks for a feed to be published again: the name of the feed and nothing else. An application's schedule of Symfony
 * Scheduler sends it — `RecurringMessage::every('6 hours', new PublishProductFeed('google'))` —, and
 * `PublishProductFeedHandler` publishes it.
 */
final readonly class PublishProductFeed
{
    public function __construct(
        public string $feed,
    ) {
    }
}
