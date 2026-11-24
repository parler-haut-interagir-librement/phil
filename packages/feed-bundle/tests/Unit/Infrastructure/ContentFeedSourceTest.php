<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\FeedBundle\Infrastructure\Content\ContentFeedSource;
use Phil\FeedBundle\Tests\Fixtures\Content\BareRow;
use Phil\FeedBundle\Tests\Fixtures\Content\InMemoryEntries;
use Phil\FeedBundle\Tests\Fixtures\Content\NewsRoot;
use Phil\FeedBundle\Tests\Fixtures\Content\NewsRow;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentFeedSource::class)]
final class ContentFeedSourceTest extends TestCase
{
    public function testTheEntriesAreThePublishedContentsWithAnAddress(): void
    {
        $entries = new InMemoryEntries([
            $this->news('a1', 'Opening', '2026-11-21T08:00:00Z', summary: 'Short', html: '<p>Body</p>'),
            $this->news('a2', 'Hidden', '2026-11-20T08:00:00Z', noIndex: true),
            $this->news('a3', 'Nowhere', '2026-11-19T08:00:00Z'),
            $this->news('a4', 'Unpublished', null),
            $this->bare('a5', '2026-11-18T08:00:00Z'),
            $this->news('a6', 'Indexed', '2026-11-17T08:00:00Z', noIndex: false),
        ], [
            'news:a1' => 'https://example.org/fr/opening',
            'news:a2' => 'https://example.org/fr/hidden',
            'news:a4' => 'https://example.org/fr/unpublished',
            'news:a5' => 'https://example.org/fr/bare',
            'news:a6' => 'https://example.org/fr/indexed',
        ]);
        $source = $this->source($entries);

        $page = $source->items(new FeedQuery(10));

        self::assertFalse($page->hasNextPage);
        self::assertSame([['news', 'fr_CA', 0, 11]], $entries->asked);
        self::assertSame([['news:a1', 'news:a3', 'news:a4', 'news:a5', 'news:a6']], $entries->addressed);
        self::assertSame(['https://example.org/fr/opening', 'https://example.org/fr/bare', 'https://example.org/fr/indexed'], array_map(static fn (FeedItem $item): string => $item->id, $page->items));

        $first = $page->items[0];
        self::assertSame(['Opening', 'https://example.org/fr/opening', 'Short', '<p>Body</p>', '2026-11-21T08:00:00+00:00', null], [$first->title, $first->url->toString(), $first->summary, $first->contentHtml, $first->published->format(\DATE_ATOM), $first->language]);
        self::assertSame(['news:a5', null, null], [$page->items[1]->title, $page->items[1]->summary, $page->items[1]->contentHtml]);
    }

    public function testAnExtraContentMeansAnOlderPage(): void
    {
        $entries = new InMemoryEntries([$this->news('a1', 'One', '2026-11-21T08:00:00Z'), $this->news('a2', 'Two', '2026-11-20T08:00:00Z'), $this->news('a3', 'Three', '2026-11-19T08:00:00Z')], ['news:a1' => 'https://example.org/1', 'news:a2' => 'https://example.org/2', 'news:a3' => 'https://example.org/3']);
        $source = $this->source($entries);

        $first = $source->items(new FeedQuery(2));
        self::assertTrue($first->hasNextPage);
        self::assertSame(['One', 'Two'], array_map(static fn (FeedItem $item): string => $item->title, $first->items));
        self::assertSame([['news:a1', 'news:a2']], $entries->addressed);

        $second = $source->items(new FeedQuery(2, 2));
        self::assertFalse($second->hasNextPage);
        self::assertSame(['news', 'fr_CA', 2, 3], $entries->asked[1]);
        self::assertSame(['Three'], array_map(static fn (FeedItem $item): string => $item->title, $second->items));

        self::assertFalse($source->items(new FeedQuery(3))->hasNextPage);
    }

    public function testATagAuthorityNamesTheEntriesByTheirReference(): void
    {
        $entries = new InMemoryEntries([$this->news('a1', 'One', '2026-11-21T08:00:00Z')], ['news:a1' => 'http://localhost/fr/one']);
        $source = new ContentFeedSource($entries, 'nouvelles', 'news', Locale::fromString('fr_CA'), $this->channel(), 'example.org,2026', true);

        self::assertSame('tag:example.org,2026:news:a1', $source->items(new FeedQuery(5))->items[0]->id);
    }

    public function testAnAddressOnLocalhostIsRefusedByDefault(): void
    {
        $entries = new InMemoryEntries([$this->news('a1', 'One', '2026-11-21T08:00:00Z')], ['news:a1' => 'http://localhost/fr/one']);

        $this->expectException(InvalidUrl::class);

        $this->source($entries)->items(new FeedQuery(5));
    }

    public function testItIsNamedAndDescribedByItsConfiguration(): void
    {
        $channel = $this->channel();
        $source = new ContentFeedSource(new InMemoryEntries([], []), 'nouvelles', 'news', Locale::fromString('fr_CA'), $channel);

        self::assertSame('nouvelles', $source->name());
        self::assertSame($channel, $source->channel());
    }

    public function testThePagesOfTheKindInTheLocaleBelongToTheFeed(): void
    {
        $source = $this->source(new InMemoryEntries([], []));
        $url = Url::fromString('https://example.org/fr/opening');

        self::assertTrue($source->includes(new PagePublished($url, 'fr-CA', true, 'news:a1')));
        self::assertTrue($source->includes(new PageRetired($url, 'fr-CA', false, 'news:a1')));
        self::assertFalse($source->includes(new PagePublished($url, 'fr', true, 'news:a1')));
        self::assertFalse($source->includes(new PagePublished($url, 'fr-CA', true, 'event:a1')));
        self::assertFalse($source->includes(new PagePublished($url, 'fr-CA', true, 'news')));
        self::assertFalse($source->includes(new PagePublished($url, 'fr-CA', true, 'news:a1:b')));
    }

    public function testTheTagAuthorities(): void
    {
        foreach (['example.org,2026', 'example.org,2026-11', 'example.org,2026-11-21', 'a,2026', 'a-b.c,2026'] as $authority) {
            self::assertMatchesRegularExpression(ContentFeedSource::TAG_AUTHORITY, $authority);
        }

        foreach (['example.org', 'Example.org,2026', '-a,2026', 'a-,2026', 'example.org,26', 'example.org,2026-1', "example.org,2026\n", 'a,2026-11-2'] as $authority) {
            self::assertDoesNotMatchRegularExpression(ContentFeedSource::TAG_AUTHORITY, $authority);
        }
    }

    private function source(InMemoryEntries $entries): ContentFeedSource
    {
        return new ContentFeedSource($entries, 'nouvelles', 'news', Locale::fromString('fr_CA'), $this->channel());
    }

    private function channel(): Channel
    {
        return new Channel('Nouvelles', Url::fromString('https://example.org/fr/nouvelles'), language: 'fr-CA');
    }

    private function news(string $id, string $title, ?string $published, ?string $summary = null, ?string $html = null, ?bool $noIndex = null): PublishedContent
    {
        $locale = Locale::fromString('fr_CA');
        $row = new NewsRow(new NewsRoot(), DimensionKey::localized($locale, Stage::Live, Version::first()));
        $row->setExcerptTitle($title);
        $row->setExcerptDescription($summary);
        $row->setSeoNoIndex($noIndex);
        $row->setWorkflowPublished(null === $published ? null : new DateTimeImmutable($published));
        $row->setBody(null === $html ? null : Body::of($html, 'html', $html));

        return new PublishedContent(ContentReference::of('news', $id), $locale, $row);
    }

    private function bare(string $id, string $published): PublishedContent
    {
        $locale = Locale::fromString('fr_CA');
        $row = new BareRow(new NewsRoot(), DimensionKey::localized($locale, Stage::Live, Version::first()));
        $row->setWorkflowPublished(new DateTimeImmutable($published));

        return new PublishedContent(ContentReference::of('news', $id), $locale, $row);
    }
}
