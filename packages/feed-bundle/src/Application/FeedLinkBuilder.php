<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application;

use Phil\FeedBundle\Application\Contract\FeedUrls;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\Url\Url;

/**
 * The links of a written feed: its own address, the hubs of the application, and, when the feed has more than one
 * page, its first page and the pages around it.
 */
final readonly class FeedLinkBuilder
{
    /**
     * @param list<Url> $hubs the WebSub hubs every feed names
     */
    public function __construct(
        private FeedUrls $urls,
        private array $hubs = [],
    ) {
    }

    public function links(string $name, FeedFormat $format, Feed $feed): FeedLinks
    {
        $page = $feed->page;
        $next = $feed->hasNextPage && $page < FeedQuery::MAX_PAGE;
        $paged = $next || $page > 1;

        return new FeedLinks(
            $this->urls->feed($name, $format, $page),
            $this->hubs,
            $paged ? $this->urls->feed($name, $format) : null,
            $page > 1 ? $this->urls->feed($name, $format, $page - 1) : null,
            $next ? $this->urls->feed($name, $format, $page + 1) : null,
        );
    }
}
