<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Messenger;

use Phil\SitesConsoleBundle\Application\Console\Puller;
use Phil\SitesConsoleBundle\Application\Message\PullSite;

/**
 * Pulls a site in a worker.
 */
final readonly class PullSiteHandler
{
    public function __construct(
        private Puller $puller,
    ) {
    }

    public function __invoke(PullSite $message): void
    {
        $this->puller->pull($message->siteId);
    }
}
