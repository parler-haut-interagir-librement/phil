<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application\Contract;

use Phil\SocialCardsBundle\Application\FetchedPage;

/**
 * Fetches the head of a page of another site, or nothing when it cannot be fetched safely: an address on a private
 * network, too slow a server, too many redirects, an answer that is not HTML.
 */
interface PageFetcher
{
    public function fetch(string $url): ?FetchedPage;
}
