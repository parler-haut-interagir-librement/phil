<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Application;

use Phil\FeedBundle\Application\FeedLinkBuilder;
use Phil\FeedBundle\Application\FeedWriters;
use Phil\FeedBundle\Application\Writer\AtomWriter;
use Phil\FeedBundle\Application\Writer\JsonFeedWriter;
use Phil\FeedBundle\Application\Writer\RssWriter;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;
use Phil\FeedBundle\Tests\Fixtures\FixedFeedUrls;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedLinkBuilder::class)]
#[CoversClass(FeedWriters::class)]
final class FeedLinkBuilderTest extends TestCase
{
    public function testASinglePageLinksToItselfAndTheHubs(): void
    {
        $hub = Url::fromString('https://hub.example.net/');
        $links = new FeedLinkBuilder(new FixedFeedUrls(), [$hub])->links('news', FeedFormat::Rss, $this->feed(1, false));

        self::assertSame(['https://example.org/feeds/news.rss', null, null, null], $this->strings($links));
        self::assertSame([$hub], $links->hubs);
        self::assertSame([], new FeedLinkBuilder(new FixedFeedUrls())->links('news', FeedFormat::Rss, $this->feed(1, false))->hubs);
    }

    public function testThePagesOfAPagedFeed(): void
    {
        $builder = new FeedLinkBuilder(new FixedFeedUrls());

        self::assertSame(['https://example.org/feeds/news.atom', 'https://example.org/feeds/news.atom', null, 'https://example.org/feeds/news.atom?page=2'], $this->strings($builder->links('news', FeedFormat::Atom, $this->feed(1, true))));
        self::assertSame(['https://example.org/feeds/news.atom?page=2', 'https://example.org/feeds/news.atom', 'https://example.org/feeds/news.atom', 'https://example.org/feeds/news.atom?page=3'], $this->strings($builder->links('news', FeedFormat::Atom, $this->feed(2, true))));
        self::assertSame(['https://example.org/feeds/news.atom?page=3', 'https://example.org/feeds/news.atom', 'https://example.org/feeds/news.atom?page=2', null], $this->strings($builder->links('news', FeedFormat::Atom, $this->feed(3, false))));
        self::assertSame(['https://example.org/feeds/news.atom?page=1000', 'https://example.org/feeds/news.atom', 'https://example.org/feeds/news.atom?page=999', null], $this->strings($builder->links('news', FeedFormat::Atom, $this->feed(1000, true))));
        self::assertNotNull($builder->links('news', FeedFormat::Atom, $this->feed(999, true))->next);
    }

    public function testTheWritersOfTheFormatsServed(): void
    {
        $atom = new AtomWriter();
        $json = new JsonFeedWriter();
        $writers = new FeedWriters([$json, new AtomWriter(), $atom]);

        self::assertSame([FeedFormat::Atom, FeedFormat::Json], $writers->formats());
        self::assertSame($atom, $writers->for(FeedFormat::Atom));
        self::assertSame($json, $writers->for(FeedFormat::Json));
        self::assertNull($writers->for(FeedFormat::Rss));
        self::assertSame(FeedFormat::cases(), new FeedWriters([new RssWriter(), $json, $atom])->formats());
    }

    private function feed(int $page, bool $next): Feed
    {
        return new Feed(new Channel('News', Url::fromString('https://example.org/')), [], $page, $next);
    }

    /**
     * @return list<string|null>
     */
    private function strings(FeedLinks $links): array
    {
        return [$links->self->toString(), $links->first?->toString(), $links->previous?->toString(), $links->next?->toString()];
    }
}
