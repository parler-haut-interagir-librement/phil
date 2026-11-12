<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Support;

use Phil\SocialCardsBundle\Application\Contract\PageFetcher;
use Phil\SocialCardsBundle\Application\FetchedPage;

/**
 * A fetcher of the pages it was given, by address; the addresses it was asked for are counted.
 */
final class FakeFetcher implements PageFetcher
{
    /** @var list<string> */
    public array $fetched = [];

    /**
     * @param array<string, FetchedPage> $pages
     */
    public function __construct(
        private readonly array $pages = [],
    ) {
    }

    public function fetch(string $url): ?FetchedPage
    {
        $this->fetched[] = $url;

        return $this->pages[$url] ?? null;
    }
}
