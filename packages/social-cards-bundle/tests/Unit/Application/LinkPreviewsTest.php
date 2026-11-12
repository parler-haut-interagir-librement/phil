<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Application;

use Phil\SocialCards\Preview\TagReader;
use Phil\SocialCardsBundle\Application\FetchedPage;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Phil\SocialCardsBundle\Tests\Support\FakeFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(LinkPreviews::class)]
#[CoversClass(FetchedPage::class)]
final class LinkPreviewsTest extends TestCase
{
    private const string PAGE = '<head><title>Plain</title><meta property="og:title" content="The post"><meta property="og:image" content="/card.png"></head>';

    public function testAPreviewIsReadWhereThePageWasServedAndCached(): void
    {
        $fetcher = new FakeFetcher(['https://example.org/post' => new FetchedPage(self::PAGE, 'https://www.example.org/post/', 'utf-8')]);
        $cache = new ArrayAdapter();
        $previews = new LinkPreviews($fetcher, new TagReader(), $cache, 60);

        $preview = $previews->preview('https://example.org/post');
        self::assertSame(['The post', 'https://www.example.org/card.png', 'https://www.example.org/post/', 'www.example.org'], [$preview?->title, $preview?->image, $preview?->url, $preview?->siteName]);
        self::assertEquals($preview, $previews->preview('https://example.org/post'));
        self::assertSame(['https://example.org/post'], $fetcher->fetched, 'The preview is kept in the cache.');

        self::assertNull($previews->preview('https://example.org/dead'));
        self::assertNull($previews->preview('https://example.org/dead'));
        self::assertSame(['https://example.org/post', 'https://example.org/dead'], $fetcher->fetched, 'A dead link is kept as nothing.');

        $item = $cache->getItem('phil_social_cards.preview.'.hash('xxh128', 'https://example.org/post'));
        self::assertTrue($item->isHit());
        self::assertSame('The post', \is_array($item->get()) ? $item->get()['title'] : null);
    }

    public function testTheCacheExpires(): void
    {
        $fetcher = new FakeFetcher(['https://example.org/post' => new FetchedPage(self::PAGE, 'https://example.org/post')]);
        $cache = new ArrayAdapter();
        $previews = new LinkPreviews($fetcher, new TagReader(), $cache, 1);
        $previews->preview('https://example.org/post');

        // An item of ArrayAdapter that expires in 1 second has an expiry within a second of now.
        $values = $cache->getValues();
        self::assertCount(1, $values);
        $reflection = new ReflectionProperty(ArrayAdapter::class, 'expiries');
        /** @var array<string, float> $expiries */
        $expiries = $reflection->getValue($cache);
        self::assertEqualsWithDelta(microtime(true) + 1, (float) array_first($expiries), 0.5);
    }

    public function testADayByDefault(): void
    {
        $cache = new ArrayAdapter();
        new LinkPreviews(new FakeFetcher(), new TagReader(), $cache)->preview('https://example.org/dead');

        /** @var array<string, float> $expiries */
        $expiries = new ReflectionProperty(ArrayAdapter::class, 'expiries')->getValue($cache);
        self::assertEqualsWithDelta(microtime(true) + 86_400, (float) array_first($expiries), 0.5);
    }

    public function testWithoutACache(): void
    {
        $fetcher = new FakeFetcher(['https://example.org/post' => new FetchedPage(self::PAGE, 'https://example.org/post')]);
        $previews = new LinkPreviews($fetcher);

        $first = $previews->preview('https://example.org/post');
        self::assertSame('The post', $first?->title);
        self::assertEquals($first, $previews->preview('https://example.org/post'));
        self::assertCount(2, $fetcher->fetched);
        self::assertSame('Plain', $previews->tags('https://example.org/post')?->title);
    }

    public function testWhatIsNotFetched(): void
    {
        $fetcher = new FakeFetcher();
        $previews = new LinkPreviews($fetcher, cache: new ArrayAdapter());

        foreach (['/relative', 'ftp://example.org/', 'javascript:alert(1)', 'https://user@example.org/', 'https://:pw@example.org/', 'https://user:pw@example.org/', 'https://example.org/'.str_repeat('a', 2029), 'not an address', 'http://127.0.0.1/', 'http://[::1]/', 'http://localhost/'] as $url) {
            self::assertNull($previews->preview($url), $url);
            self::assertNull($previews->tags($url), $url);
        }

        self::assertSame([], $fetcher->fetched);
        self::assertSame([], $this->cached($previews), 'An address that is not fetched is not cached either.');
        self::assertTrue(LinkPreviews::isFetchable('https://example.org/'.str_repeat('a', 2028)));
        self::assertTrue(LinkPreviews::isFetchable('http://example.org/'));
    }

    public function testThePreviewOfThePastedLinkIsThatOfTheFirstLinkThatCanBeFetched(): void
    {
        $fetcher = new FakeFetcher([
            'https://example.org/post' => new FetchedPage(self::PAGE, 'https://example.org/post'),
            'https://example.net/other' => new FetchedPage('<title>Other</title>', 'https://example.net/other'),
        ]);
        $previews = new LinkPreviews($fetcher);

        self::assertSame('The post', $previews->firstIn('Look (http://10.0.0.1/admin) at https://example.org/post, and https://example.net/other.')?->title);
        self::assertSame(['https://example.org/post'], $fetcher->fetched, 'One link is fetched, the first one that can be.');
        self::assertNull($previews->firstIn('No link, only example.org and http://localhost/.'));
        self::assertNull($previews->firstIn('https://example.com/dead'));
        self::assertSame(['https://example.org/post', 'https://example.com/dead'], $fetcher->fetched);
    }

    public function testACharsetIsPassedOn(): void
    {
        $fetcher = new FakeFetcher(['https://example.org/' => new FetchedPage("<meta property=\"og:title\" content=\"\xE9t\xE9\">", 'https://example.org/', 'iso-8859-1')]);

        self::assertSame('été', new LinkPreviews($fetcher)->preview('https://example.org/')?->title);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function cached(LinkPreviews $previews): array
    {
        $cache = new ReflectionProperty(LinkPreviews::class, 'cache')->getValue($previews);
        self::assertInstanceOf(ArrayAdapter::class, $cache);

        return $cache->getValues();
    }
}
