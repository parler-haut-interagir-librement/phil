<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\WebSub;

use Phil\FeedBundle\Application\Feeds;
use Phil\Publishing\Contract\PageEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Listens to `PagePublished` and `PageRetired` of `phil/publishing`: each feed that lists, or listed, the page gets an
 * `AnnounceFeedUpdate`, which the application routes to an asynchronous transport. Registered only when a hub is
 * configured.
 */
final readonly class AnnounceChangedFeeds
{
    public function __construct(
        private Feeds $feeds,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(PageEvent $page): void
    {
        foreach ($this->feeds->including($page) as $name) {
            $this->bus->dispatch(new AnnounceFeedUpdate($name));
        }
    }
}
