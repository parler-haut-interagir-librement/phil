<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Contract;

use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;

/**
 * Writes a feed in one format. The three writers of the bundle read the same `Feed`, so that its Atom, RSS and JSON
 * Feed say the same thing.
 */
interface FeedWriter
{
    public function format(): FeedFormat;

    /**
     * The document, in UTF-8.
     */
    public function write(Feed $feed, FeedLinks $links): string;
}
