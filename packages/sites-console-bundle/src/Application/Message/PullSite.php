<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Message;

/**
 * Read the versions and the health of a site.
 */
final readonly class PullSite
{
    public function __construct(
        public string $siteId,
    ) {
    }
}
