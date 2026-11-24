<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures;

use Override;
use Phil\FeedBundle\Application\Contract\FeedUrls;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\Url\Url;

/**
 * The addresses of the feeds under https://example.org/feeds/, as the route of the bundle writes them.
 */
final class FixedFeedUrls implements FeedUrls
{
    #[Override]
    public function feed(string $name, FeedFormat $format, int $page = 1): Url
    {
        return Url::fromString('https://example.org/feeds/'.$name.'.'.$format->value.($page > 1 ? '?page='.$page : ''));
    }
}
