<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Contract;

use Phil\FeedBundle\Domain\FeedFormat;
use Phil\Url\Url;

/**
 * The absolute address of a feed in a format, page 1 without a page number: what a feed names as `self`, what a page
 * links to, and the topic a hub is told about.
 */
interface FeedUrls
{
    public function feed(string $name, FeedFormat $format, int $page = 1): Url;
}
