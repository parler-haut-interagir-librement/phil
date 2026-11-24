<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures;

use Override;
use Phil\FeedBundle\Application\Contract\FeedSource;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedPage;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\Publishing\Contract\PageEvent;
use Phil\Url\Url;

/**
 * A source of fixed entries, paged by the query, that includes the pages whose owner identifier starts with its prefix.
 * It records the queries it is asked.
 */
final class StaticSource implements FeedSource
{
    /** @var list<FeedQuery> */
    public array $queries = [];

    /**
     * @param list<FeedItem> $items
     */
    public function __construct(
        private readonly string $name,
        private readonly array $items = [],
        private readonly string $prefix = 'news:',
        private readonly bool $saysNext = false,
        private readonly int $extra = 0,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function channel(): Channel
    {
        return new Channel(ucfirst($this->name), Url::fromString('https://example.org/'.$this->name));
    }

    #[Override]
    public function items(FeedQuery $query): FeedPage
    {
        $this->queries[] = $query;

        return new FeedPage(\array_slice($this->items, $query->offset(), $query->limit + $this->extra), $this->saysNext);
    }

    #[Override]
    public function includes(PageEvent $page): bool
    {
        return str_starts_with($page->ownerId, $this->prefix);
    }
}
