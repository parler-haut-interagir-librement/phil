<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit;

use Phil\Publishing\PagePublished;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\ChannelIndexing;
use Phil\SeoCheck\Exception\InvalidPage;
use Phil\SeoCheck\Markup\MarkupReader;
use Phil\SeoCheck\PageCheckFactory;
use Phil\SeoCheck\Tests\Support\MemoryRenderedPages;
use Phil\SeoCheck\Tests\Support\Pages;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckedPage::class)]
#[CoversClass(PageCheckFactory::class)]
final class CheckedPageTest extends TestCase
{
    public function testAPageHasARendering(): void
    {
        $this->expectException(InvalidPage::class);
        $this->expectExceptionMessageIsOrContains('A page is checked from one rendering at least.');

        new CheckedPage(Url::fromString(Pages::URL), 'fr', true, []);
    }

    public function testThePageAtItsAddressComesFirst(): void
    {
        $primary = Pages::rendered(Pages::html());
        $page = CheckedPage::of(Url::fromString(Pages::URL), 'fr', true, [$primary, Pages::rendered('', 'https://example.org/')], [], new MarkupReader(maxElements: 0));

        self::assertSame($primary, $page->primary()->page);
        self::assertCount(2, $page->renderings);
        self::assertSame([], $page->primary()->markup->titles);
    }

    public function testEachChannelFollowsThePageUnlessTheApplicationSaysOtherwise(): void
    {
        $page = Pages::checked(Pages::html(), indexable: true, channels: ['wholesale' => false]);

        self::assertTrue($page->isIndexableIn(null));
        self::assertTrue($page->isIndexableIn('retail'));
        self::assertFalse($page->isIndexableIn('wholesale'));
        self::assertFalse(Pages::checked(Pages::html(), indexable: false, channels: ['retail' => true])->isIndexableIn(null));
        self::assertTrue(Pages::checked(Pages::html(), indexable: false, channels: ['retail' => true])->isIndexableIn('retail'));
    }

    public function testThePageIsBuiltFromTheEvent(): void
    {
        $event = new PagePublished(Url::fromString(Pages::URL), 'fr-CA', false, 'news:42');
        $channels = new class implements ChannelIndexing {
            /** @var list<string> */
            public array $asked = [];

            public function isIndexable(PagePublished $event, string $channel): ?bool
            {
                $this->asked[] = $channel;

                return 'retail' === $channel ? true : null;
            }
        };
        $factory = new PageCheckFactory(new MemoryRenderedPages([
            Pages::rendered(Pages::html()),
            Pages::rendered(Pages::html(), 'https://www.example.org/fr/news/opening', channel: 'retail'),
            Pages::rendered(Pages::html(), 'https://kiosk.example.org/fr/news/opening', channel: 'kiosk'),
            Pages::rendered(Pages::html(), 'https://kiosk.example.org/fr/news/opening?a=1', channel: 'kiosk'),
            Pages::rendered(Pages::html(), 'https://www.example.org/fr/news/opening?a=1', channel: 'retail'),
        ]), $channels);

        $page = $factory->fromEvent($event);

        self::assertInstanceOf(CheckedPage::class, $page);
        self::assertSame(Pages::URL, $page->canonicalUrl->toString());
        self::assertSame('fr-CA', $page->locale);
        self::assertFalse($page->indexable);
        self::assertCount(5, $page->renderings);
        self::assertSame(['retail' => true], $page->indexableByChannel);
        self::assertSame(['retail', 'kiosk'], $channels->asked);
        self::assertSame(['Ouverture de la boutique d’hiver'], $page->primary()->markup->titles);
    }

    public function testWithoutChannelIndexingEveryChannelFollowsThePage(): void
    {
        $factory = new PageCheckFactory(new MemoryRenderedPages([Pages::rendered(Pages::html(), channel: 'retail')]), reader: new MarkupReader(maxElements: 0));
        $page = $factory->fromEvent(new PagePublished(Url::fromString(Pages::URL), 'fr', true, 'news:42'));

        self::assertInstanceOf(CheckedPage::class, $page);
        self::assertSame([], $page->indexableByChannel);
        self::assertSame([], $page->primary()->markup->titles);
    }

    public function testAPageTheApplicationDoesNotRenderIsNotChecked(): void
    {
        self::assertNull(new PageCheckFactory(new MemoryRenderedPages())->fromEvent(new PagePublished(Url::fromString(Pages::URL), 'fr', true, 'news:42')));
    }
}
