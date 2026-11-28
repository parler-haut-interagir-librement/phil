<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Functional;

use Override;
use Phil\FeedBundle\Infrastructure\Http\FeedController;
use Phil\FeedBundle\Infrastructure\Routing\RouterFeedUrls;
use Phil\FeedBundle\Infrastructure\Twig\FeedExtension;
use Phil\FeedBundle\Infrastructure\Twig\FeedRuntime;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceChangedFeeds;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate;
use Phil\FeedBundle\PhilFeedBundle;
use Phil\FeedBundle\Tests\Fixtures\JsonFeedSchema;
use Phil\FeedBundle\Tests\Functional\App\TestKernel;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

#[CoversClass(PhilFeedBundle::class)]
#[CoversClass(FeedController::class)]
#[CoversClass(RouterFeedUrls::class)]
#[CoversClass(FeedExtension::class)]
#[CoversClass(FeedRuntime::class)]
final class FeedBundleTest extends WebTestCase
{
    private const array SERVER = ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on'];

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testEachFormatIsServedWithItsMediaType(): void
    {
        $client = self::createClient(server: self::SERVER);

        foreach (['atom' => 'application/atom+xml; charset=UTF-8', 'rss' => 'application/rss+xml; charset=UTF-8', 'json' => 'application/feed+json; charset=UTF-8'] as $format => $type) {
            $client->request('GET', '/feeds/news.'.$format);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', $type);
            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
            self::assertResponseHeaderSame('Last-Modified', 'Sat, 21 Nov 2026 08:00:00 GMT');
        }

        $client->request('GET', '/feeds/news.atom');
        $atom = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<link rel="self" type="application/atom+xml" href="https://example.com/feeds/news.atom"/>', $atom);
        self::assertStringContainsString('<link rel="hub" href="https://hub.example.net/"/>', $atom);
        self::assertStringContainsString('<link rel="next" type="application/atom+xml" href="https://example.com/feeds/news.atom?page=2"/>', $atom);
        self::assertSame(2, substr_count($atom, '<entry>'));

        $client->request('GET', '/feeds/news.json');
        JsonFeedSchema::assertValid((string) $client->getResponse()->getContent());
    }

    public function testAnOlderPageIsServed(): void
    {
        $client = self::createClient(server: self::SERVER);

        $client->request('GET', '/feeds/news.rss?page=2');
        self::assertResponseIsSuccessful();
        $rss = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<atom:link rel="self" type="application/rss+xml" href="https://example.com/feeds/news.rss?page=2"/>', $rss);
        self::assertStringContainsString('<atom:link rel="previous" type="application/rss+xml" href="https://example.com/feeds/news.rss"/>', $rss);
        self::assertSame(1, substr_count($rss, '<item>'));
        self::assertStringNotContainsString('rel="next"', $rss);
    }

    public function testWhatIsNotAFeedIsNotFound(): void
    {
        $client = self::createClient(server: self::SERVER);

        foreach (['/feeds/nope.atom', '/feeds/news.xml', '/feeds/News.atom', '/feeds/news.atom?page=3', '/feeds/news.atom?page=1', '/feeds/news.atom?page=02', '/feeds/news.atom?page=1001', '/feeds/news.atom?page=x', '/feeds/news.atom?page[]=2', '/feeds/quiet.atom?page=2'] as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path);
        }

        $client->request('POST', '/feeds/news.atom');
        self::assertResponseStatusCodeSame(405);
    }

    public function testAnEmptyFeedIsServedWithoutADate(): void
    {
        $client = self::createClient(server: self::SERVER);

        $client->request('GET', '/feeds/quiet.atom');
        self::assertResponseIsSuccessful();
        self::assertFalse($client->getResponse()->headers->has('Last-Modified'));
        self::assertStringContainsString('<updated>1970-01-01T00:00:00Z</updated>', (string) $client->getResponse()->getContent());
    }

    /**
     * A feed may be kept by caches, carries an ETag of its format and content, and is a 304 to a client that holds it.
     */
    public function testAServedFeedIsCacheable(): void
    {
        $client = self::createClient(server: self::SERVER);

        $client->request('GET', '/feeds/news.atom');

        $response = $client->getResponse();
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('s-maxage'));
        $etag = (string) $response->getEtag();
        self::assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', $etag);

        $client->request('GET', '/feeds/news.rss');
        self::assertNotSame($etag, $client->getResponse()->getEtag());
        $client->request('GET', '/feeds/news.atom?page=2');
        self::assertNotSame($etag, $client->getResponse()->getEtag());

        $client->request('GET', '/feeds/news.atom', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
        self::assertSame('', $client->getResponse()->getContent());

        $client->request('GET', '/feeds/news.atom', server: ['HTTP_IF_MODIFIED_SINCE' => 'Sat, 21 Nov 2026 08:00:00 GMT']);
        self::assertResponseStatusCodeSame(304);

        $client->request('GET', '/feeds/news.atom', server: ['HTTP_IF_MODIFIED_SINCE' => 'Sat, 21 Nov 2026 07:59:59 GMT']);
        self::assertResponseStatusCodeSame(200);

        $client->request('GET', '/feeds/news.atom', server: ['HTTP_IF_NONE_MATCH' => '"other"']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testThePageLinksToItsFeeds(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame(
            '<link rel="alternate" type="application/atom+xml" title="News &quot;&amp;&quot; more (Atom)" href="https://example.com/feeds/news.atom">'."\n"
            .'<link rel="alternate" type="application/rss+xml" title="News &quot;&amp;&quot; more (RSS)" href="https://example.com/feeds/news.rss">'."\n"
            .'<link rel="alternate" type="application/feed+json" title="News &quot;&amp;&quot; more (JSON Feed)" href="https://example.com/feeds/news.json">',
            $twig->createTemplate("{{ phil_feed_links('news') }}")->render(),
        );
        self::assertSame(
            '<link rel="alternate" type="application/atom+xml" title="News &quot;&amp;&quot; more (Atom)" href="https://example.com/feeds/news.atom">'."\n"
            .'<link rel="alternate" type="application/atom+xml" title="Quiet (Atom)" href="https://example.com/feeds/quiet.atom">',
            $twig->createTemplate("{{ phil_feed_links(formats: ['atom', 'xml']) }}")->render(),
        );
        self::assertSame(2, substr_count($twig->createTemplate("{{ phil_feed_links(['quiet'], ['rss', 'json']) }}")->render(), '<link '));
        self::assertSame('', $twig->createTemplate('{{ phil_feed_links([]) }}')->render());
    }

    public function testAPublishedPageAnnouncesItsFeedsByName(): void
    {
        self::bootKernel();
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $dispatcher->dispatch(new PagePublished(Url::fromString('https://example.com/news/3'), 'en', true, 'news:3'));
        $dispatcher->dispatch(new PageRetired(Url::fromString('https://example.com/news/1'), 'en', true, 'news:1'));
        $dispatcher->dispatch(new PagePublished(Url::fromString('https://example.com/about'), 'en', true, 'page:about'));

        self::assertQueuedMessageCount(2, 'async', AnnounceFeedUpdate::class);
        $messages = array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), self::getQueuedMessages('async'));
        self::assertEquals([new AnnounceFeedUpdate('news'), new AnnounceFeedUpdate('news')], $messages);
    }

    public function testWithoutAHubNothingIsAnnounced(): void
    {
        self::bootKernel(['environment' => 'nohub']);

        self::assertFalse(self::getContainer()->has(AnnounceChangedFeeds::class));
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->dispatch(new PagePublished(Url::fromString('https://example.com/news/3'), 'en', true, 'news:3'));

        self::assertQueuedMessageCount(0, 'async');
    }

    public function testOnlyTheConfiguredFormatsAreServed(): void
    {
        $client = self::createClient(['environment' => 'nohub'], self::SERVER);

        $client->request('GET', '/feeds/news.atom');
        self::assertResponseIsSuccessful();
        self::assertFalse($client->getResponse()->headers->hasCacheControlDirective('public'));
        self::assertStringNotContainsString('rel="hub"', (string) $client->getResponse()->getContent());
        self::assertSame(20, substr_count((string) $client->getResponse()->getContent(), '<entry>') + 17);

        $client->request('GET', '/feeds/news.rss');
        self::assertResponseStatusCodeSame(404);
    }
}
