<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Functional\App;

use Override;
use Phil\FeedBundle\Application\Contract\FeedSource;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\FeedPage;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\Publishing\Contract\PageEvent;
use Phil\Url\Url;

/**
 * A feed with nothing in it yet.
 */
final class EmptySource implements FeedSource
{
    #[Override]
    public function name(): string
    {
        return 'quiet';
    }

    #[Override]
    public function channel(): Channel
    {
        return new Channel('Quiet', Url::fromString('https://example.com/quiet'));
    }

    #[Override]
    public function items(FeedQuery $query): FeedPage
    {
        return new FeedPage([]);
    }

    #[Override]
    public function includes(PageEvent $page): bool
    {
        return false;
    }
}
