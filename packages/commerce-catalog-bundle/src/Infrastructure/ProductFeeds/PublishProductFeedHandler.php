<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds;

/**
 * Publishes the feed a `PublishProductFeed` names. The bundle tags it as a handler of Symfony Messenger when Messenger
 * is installed.
 */
final readonly class PublishProductFeedHandler
{
    public function __construct(
        private ProductFeeds $feeds,
    ) {
    }

    public function __invoke(PublishProductFeed $message): void
    {
        $this->feeds->publish($message->feed);
    }
}
