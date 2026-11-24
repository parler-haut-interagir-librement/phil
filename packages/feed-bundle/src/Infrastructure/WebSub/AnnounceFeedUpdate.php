<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\WebSub;

/**
 * Tell the WebSub hubs that a feed changed. The message names the feed and nothing else: the handler reads the hubs
 * from the configuration and writes the feed's addresses itself, so a queue holds neither an address nor a content.
 */
final readonly class AnnounceFeedUpdate
{
    public function __construct(
        public string $feed,
    ) {
    }
}
