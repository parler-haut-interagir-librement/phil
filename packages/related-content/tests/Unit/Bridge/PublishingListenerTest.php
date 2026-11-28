<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Unit\Bridge;

use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\RelatedContent\Bridge\Publishing\PublishingListener;
use Phil\RelatedContent\Index\KeywordIndex;
use Phil\RelatedContent\Item;
use Phil\RelatedContent\Neighbourhood;
use Phil\RelatedContent\RelatedContent;
use Phil\RelatedContent\RelevanceRules;
use Phil\RelatedContent\Store\InMemoryRelatedStore;
use Phil\RelatedContent\Tests\Support\MemorySource;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublishingListener::class)]
final class PublishingListenerTest extends TestCase
{
    public function testThePageEventsComputeTheNeighboursOfTheirOwner(): void
    {
        $source = new MemorySource(new Item('article:1', ['topics' => ['x']]), new Item('article:2', ['topics' => ['x']]));
        $store = new InMemoryRelatedStore();
        $listener = new PublishingListener(new RelatedContent($source, new Neighbourhood($source, new RelevanceRules([new KeywordIndex('topics', 100.0)])), $store));
        $url = Url::fromString('https://example.org/en/articles/one');

        self::assertSame(['article:2'], $listener->onPagePublished(new PagePublished($url, 'en', true, 'article:1'))->recomputed);
        self::assertSame(['article:2'], $store->find('article:1')?->ids());

        $source->retire('article:1');

        self::assertSame(['article:2'], $listener->onPageRetired(new PageRetired($url, 'en', true, 'article:1'))->recomputed);
        self::assertNull($store->find('article:1'));
        self::assertSame([], $store->find('article:2')?->ids());
    }
}
