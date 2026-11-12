<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Http;

use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Normalizer\ExcerptNormalizer;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Application\Rendering\TemplateResolver;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Console\PreviewLinkCommand;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\ContentBundle\Infrastructure\Http\FeedController;
use Phil\ContentBundle\Infrastructure\Http\PreviewController;
use Phil\ContentBundle\Infrastructure\Http\PreviewLinks;
use Phil\ContentBundle\Infrastructure\Http\ResourceRoutes;
use Phil\ContentBundle\Infrastructure\Http\Xml;
use Phil\ContentBundle\Infrastructure\Sitemap\ContentUrlProvider;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\LocaleDimensions\Locale;
use Phil\Sitemap\Sitemap;
use Phil\Sitemap\XmlWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(ContentUrlGenerator::class)]
#[CoversClass(Xml::class)]
#[CoversClass(ContentUrlProvider::class)]
#[CoversClass(FeedController::class)]
#[CoversClass(PreviewLinks::class)]
#[CoversClass(PreviewController::class)]
#[CoversClass(PreviewLinkCommand::class)]
#[CoversClass(ResourceRoutes::class)]
final class PublicationEndpointsTest extends TestCase
{
    private InMemoryContents $contents;

    private ContentManager $manager;

    private UrlGenerator $router;

    private ResourceRoutes $resourceRoutes;

    private PublishedContents $published;

    private ContentUrlGenerator $urls;

    private string $defaultLocale;

    #[Override]
    protected function setUp(): void
    {
        // `Request::setLocale()` sets PHP's default locale too: put it back, for the tests that run after.
        $this->defaultLocale = \Locale::getDefault();
        $this->contents = new InMemoryContents();
        $this->manager = $this->contents->manager;
        $routes = new PhpFileLoader(new FileLocator(\dirname(__DIR__, 4).'/config'))->load('routes.php');
        $routes->add('example_show', new Route('/{_locale}/example/{slug}'));

        $this->router = new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https'));
        $this->resourceRoutes = new ResourceRoutes(['example' => 'example_show', 'other' => 'other_show']);
        $this->published = $this->contents->published;
        $this->urls = new ContentUrlGenerator($this->contents->routes, $this->resourceRoutes, $this->router);
    }

    #[Override]
    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);
    }

    public function testTheSitemapListsEachLocalesPagesWithTheirAlternates(): void
    {
        $page = $this->manager->create('example', 'fr', ['slug' => 'bonjour', 'excerptTitle' => 'Bonjour']);
        $this->manager->edit($page, 'en', ['slug' => 'hello']);
        $this->manager->edit($page, 'fr_CA', ['slug' => 'allo']);
        $this->manager->publish($page, 'fr');
        $this->manager->publish($page, 'fr_CA');

        $this->contents->clock->modify('+1 day');
        $this->manager->publish($page, 'en');
        $alone = $this->manager->create('example', 'fr', ['slug' => 'seul']);
        $this->manager->publish($alone, 'fr');
        $hidden = $this->manager->create('example', 'fr', ['slug' => 'cache', 'seoHideInSitemap' => true]);
        $this->manager->publish($hidden, 'fr');
        $unrouted = $this->manager->create('example', 'fr_CA', []);
        $this->manager->publish($unrouted, 'fr_CA');

        $provider = new ContentUrlProvider($this->contents->locales, $this->resourceRoutes, $this->published, $this->urls);
        $sitemap = new Sitemap();
        $provider->provide($sitemap, null);
        $other = new Sitemap('other');
        $provider->provide($other, 'other');
        $none = new Sitemap();
        $provider->provide($none, 'other');

        self::assertSame(['example'], array_keys($sitemap->sections()));
        $last = new Sitemap();
        new ContentUrlProvider($this->contents->locales, new ResourceRoutes(['other' => 'other_show', 'example' => 'example_show']), $this->published, $this->urls)->provide($last, 'example');
        self::assertCount(4, $last->sections()['example'] ?? []);
        self::assertSame([[], []], [$other->sections(), $none->sections()]);
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
              <url>
                <loc>https://example.com/fr_CA/example/allo</loc>
                <lastmod>2026-10-31T16:00:00+00:00</lastmod>
                <xhtml:link rel="alternate" hreflang="fr-CA" href="https://example.com/fr_CA/example/allo"/>
                <xhtml:link rel="alternate" hreflang="fr" href="https://example.com/fr/example/bonjour"/>
                <xhtml:link rel="alternate" hreflang="en" href="https://example.com/en/example/hello"/>
              </url>
              <url>
                <loc>https://example.com/fr/example/bonjour</loc>
                <lastmod>2026-10-31T16:00:00+00:00</lastmod>
                <xhtml:link rel="alternate" hreflang="fr-CA" href="https://example.com/fr_CA/example/allo"/>
                <xhtml:link rel="alternate" hreflang="fr" href="https://example.com/fr/example/bonjour"/>
                <xhtml:link rel="alternate" hreflang="en" href="https://example.com/en/example/hello"/>
              </url>
              <url>
                <loc>https://example.com/en/example/hello</loc>
                <lastmod>2026-11-01T17:00:00+00:00</lastmod>
                <xhtml:link rel="alternate" hreflang="fr-CA" href="https://example.com/fr_CA/example/allo"/>
                <xhtml:link rel="alternate" hreflang="fr" href="https://example.com/fr/example/bonjour"/>
                <xhtml:link rel="alternate" hreflang="en" href="https://example.com/en/example/hello"/>
              </url>
              <url>
                <loc>https://example.com/fr/example/seul</loc>
                <lastmod>2026-11-01T17:00:00+00:00</lastmod>
              </url>
            </urlset>

            XML, XmlWriter::urlset($sitemap->sections()['example']));
    }

    public function testAFeedListsTheLatestEntriesOfALocale(): void
    {
        foreach (['un' => '2026-11-01', 'deux' => '2026-11-03', 'trois' => '2026-11-02'] as $slug => $day) {
            $this->contents->clock->modify($day.' 12:00');
            $reference = $this->manager->create('example', 'fr', ['slug' => $slug, 'excerptTitle' => 'Titre « '.$slug.' » "l\'', 'excerptDescription' => 'un' === $slug ? null : 'Résumé de '.$slug]);
            $this->manager->publish($reference, 'fr');
        }

        $unrouted = $this->manager->create('example', 'fr', []);
        $this->manager->publish($unrouted, 'fr');
        $request = Request::create('https://example.com/feeds/example.atom');
        $request->setLocale('fr');
        $request->attributes->set('title', 'Nouvelles & annonces');

        $response = new FeedController($this->contents->locales, $this->resourceRoutes, $this->published, $this->urls, 2)($request, 'example');

        self::assertSame('application/atom+xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom" xml:lang="fr">
              <id>https://example.com/feeds/example.atom</id>
              <title>Nouvelles &amp; annonces</title>
              <link rel="self" href="https://example.com/feeds/example.atom"/>
              <updated>2026-11-03T17:00:00+00:00</updated>
              <entry>
                <id>https://example.com/fr/example/deux</id>
                <title>Titre « deux » &quot;l&apos;</title>
                <link rel="alternate" href="https://example.com/fr/example/deux"/>
                <updated>2026-11-03T17:00:00+00:00</updated>
                <summary>Résumé de deux</summary>
              </entry>
              <entry>
                <id>https://example.com/fr/example/trois</id>
                <title>Titre « trois » &quot;l&apos;</title>
                <link rel="alternate" href="https://example.com/fr/example/trois"/>
                <updated>2026-11-02T17:00:00+00:00</updated>
                <summary>Résumé de trois</summary>
              </entry>
            </feed>

            XML, $response->getContent());

        $this->contents->clock->modify('2026-11-04 12:00');
        $latestUnrouted = $this->manager->create('example', 'fr', ['excerptTitle' => 'Sans adresse']);
        $this->manager->publish($latestUnrouted, 'fr');
        $three = new FeedController($this->contents->locales, $this->resourceRoutes, $this->published, $this->urls, 3)($request, 'example');
        self::assertSame(3, substr_count((string) $three->getContent(), '<entry>'), 'A content without a slug takes no place in the feed.');

        $all = new FeedController($this->contents->locales, $this->resourceRoutes, $this->published, $this->urls, 20)($request, 'example');
        self::assertSame(3, substr_count((string) $all->getContent(), '<entry>'));
        self::assertStringContainsString("<title>Titre « un » &quot;l&apos;</title>\n    <link rel=\"alternate\" href=\"https://example.com/fr/example/un\"/>\n    <updated>2026-11-01T17:00:00+00:00</updated>\n  </entry>", (string) $all->getContent());
    }

    public function testTheUrlsOfManyContentsAreGeneratedAtOnce(): void
    {
        $first = $this->manager->create('example', 'fr', ['slug' => 'premier']);
        $this->manager->publish($first, 'fr');
        $second = $this->manager->create('example', 'fr', ['slug' => 'second']);
        $this->manager->publish($second, 'fr');
        $draft = $this->manager->create('example', 'fr', ['slug' => 'brouillon']);
        $other = ContentReference::of('other', '1');
        $unmounted = ContentReference::of('unmounted', '1');

        self::assertSame(
            ['example:1' => 'https://example.com/fr/example/premier', 'example:2' => 'https://example.com/fr/example/second'],
            $this->sorted($this->urls->generateAll([$first, $second, $draft, $other, $unmounted], Locale::fromString('fr'), true)),
        );
        self::assertSame(['example:2' => '/fr/example/second'], $this->urls->generateAll([$second], Locale::fromString('fr')));
        self::assertSame(['example:2' => '/fr/example/second'], $this->urls->generateAll([$unmounted, $second], Locale::fromString('fr')), 'A kind mounted on no route leaves the next kinds their URLs.');
        self::assertSame([], $this->urls->generateAll([], Locale::fromString('fr')));
        self::assertSame([], $this->urls->generateAll([$first], Locale::fromString('en')));
    }

    public function testAnEmptyFeedHasTheKindForTitle(): void
    {
        $request = Request::create('https://example.com/feeds/example.atom');
        $request->setLocale('en');

        $content = (string) new FeedController($this->contents->locales, $this->resourceRoutes, $this->published, $this->urls, 20)($request, 'example')->getContent();

        self::assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="en">', $content);
        self::assertStringContainsString('<title>example</title>', $content);
        self::assertStringContainsString('<updated>1970-01-01T00:00:00+00:00</updated>', $content);
        self::assertStringNotContainsString('<entry>', $content);
        $canadian = Request::create('https://example.com/feeds/example.atom');
        $canadian->setLocale('fr_CA');
        self::assertStringContainsString('xml:lang="fr-CA"', (string) new FeedController($this->contents->locales, $this->resourceRoutes, $this->published, $this->urls, 20)($canadian, 'example')->getContent());
    }

    public function testAFeedOfAnUnknownKindOrLocaleIsNotFound(): void
    {
        $request = Request::create('/feeds/example.atom');

        foreach ([['news', 'fr', 'No route serves the kind of content "news".'], ['example', 'de', 'The locale "de" is not served.'], ['example', 'not a locale', '"not a locale" is not a locale']] as [$kind, $locale, $message]) {
            $request->setLocale($locale);

            try {
                new FeedController($this->contents->locales, $this->resourceRoutes, $this->published, $this->urls, 20)($request, $kind);
                self::fail($message);
            } catch (NotFoundHttpException $notFound) {
                self::assertStringStartsWith($message, $notFound->getMessage());
            }
        }
    }

    public function testADraftIsPreviewedAtItsSignedLink(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news', 'excerptTitle' => 'Bientôt']);
        $url = $this->links(new MockClock('2026-11-04 12:00 UTC'))->url($reference, Locale::fromString('fr'));
        $expires = 1_793_793_600 + 3600;

        self::assertSame(\sprintf('https://example.com/preview/example:1/fr?expires=%d&signature=%s', $expires, hash_hmac('sha256', 'example:1|fr|'.$expires, 'secret')), $url);

        $response = $this->preview(new MockClock('2026-11-04 12:30 UTC'))(Request::create($url), 'example:1', 'fr');
        self::assertSame('news|Bientôt|example:1|fr|preview|stayed', $response->getContent());
        self::assertSame(['noindex, nofollow', 'no-store, private'], [$response->headers->get('X-Robots-Tag'), $response->headers->get('Cache-Control')]);
        self::assertSame(200, $this->preview(new MockClock('2026-11-04 13:00 UTC'))(Request::create($url), 'example:1', 'fr')->getStatusCode());
        self::assertSame(1_793_793_600 + 604_800, Request::create(new PreviewLinks($this->router, 'secret', clock: new MockClock('2026-11-04 12:00 UTC'))->url($reference, Locale::fromString('fr')))->query->getInt('expires'));
        self::assertEqualsWithDelta(time() + 604_800, (int) Request::create(new PreviewLinks($this->router, 'secret', new NativeClock())->url($reference, Locale::fromString('fr')))->query->get('expires'), 5);
    }

    public function testAPreviewLinkThatIsNotRightIsNotFound(): void
    {
        $reference = $this->manager->create('example', 'fr', ['excerptTitle' => 'Bientôt']);
        $url = $this->links(new MockClock('2026-11-04 12:00 UTC'))->url($reference, Locale::fromString('fr'));
        $request = Request::create($url);
        $signature = $request->query->getString('signature');
        $sign = static fn (string $reference, string $locale): string => hash_hmac('sha256', $reference.'|'.$locale.'|'.(1_793_793_600 + 3600), 'secret');
        $now = new MockClock('2026-11-04 12:00 UTC');

        foreach ([
            'expired' => [new MockClock('2026-11-04 13:00:01 UTC'), $request, 'example:1', 'fr', 'This preview link is not signed, or expired.'],
            'another content' => [$now, $request, 'example:2', 'fr', 'This preview link is not signed, or expired.'],
            'another locale' => [$now, $request, 'example:1', 'en', 'This preview link is not signed, or expired.'],
            'not signed' => [$now, Request::create('https://example.com/p'), 'example:1', 'fr', 'This preview link is not signed, or expired.'],
            'no draft' => [$now, Request::create('https://example.com/p?expires='.(1_793_793_600 + 3600).'&signature='.$sign('example:1', 'en')), 'example:1', 'en', '"example:1" has no draft in "en".'],
            'no content' => [$now, Request::create('https://example.com/p?expires='.(1_793_793_600 + 3600).'&signature='.$sign('example:9', 'fr')), 'example:9', 'fr', '"example:9" has no draft in "fr".'],
            'no reference' => [$now, Request::create('https://example.com/p?expires='.(1_793_793_600 + 3600).'&signature='.$sign('example', 'fr')), 'example', 'fr', '"example" is not a content reference'],
            'no locale' => [$now, Request::create('https://example.com/p?expires='.(1_793_793_600 + 3600).'&signature='.$sign('example:1', '1')), 'example:1', '1', '"1" is not a locale'],
        ] as $case => [$clock, $given, $content, $locale, $message]) {
            try {
                $this->preview($clock)($given, $content, $locale);
                self::fail($case);
            } catch (NotFoundHttpException $notFound) {
                self::assertStringStartsWith($message, $notFound->getMessage(), $case);
            }
        }

        self::assertSame(64, \strlen($signature));
    }

    public function testTheCommandPrintsAPreviewLink(): void
    {
        $command = new PreviewLinkCommand($this->links(new MockClock('2026-11-04 12:00 UTC')));
        self::assertSame('phil:content:preview-link', $command->getName());

        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute(['reference' => 'example:1', 'locale' => 'fr']));
        self::assertStringStartsWith('https://example.com/preview/example:1/fr?expires=1793797200&signature=', $tester->getDisplay());

        $refused = new CommandTester($command);
        self::assertSame(Command::FAILURE, $refused->execute(['reference' => 'example', 'locale' => 'fr']));
        self::assertStringContainsString('[ERROR] "example" is not a content reference', $refused->getDisplay());

        $locale = new CommandTester($command);
        self::assertSame(Command::FAILURE, $locale->execute(['reference' => 'example:1', 'locale' => '1']));
        self::assertStringContainsString('[ERROR] "1" is not a locale', $locale->getDisplay());
    }

    public function testTheKindsAreListed(): void
    {
        self::assertSame(['example', 'other'], $this->resourceRoutes->resourceKeys());
        self::assertSame(ContentReference::class, ContentReference::of('example', '1')::class);
    }

    private function links(MockClock $clock): PreviewLinks
    {
        return new PreviewLinks($this->router, 'secret', $clock, 3600);
    }

    private function preview(MockClock $clock): PreviewController
    {
        return new PreviewController(
            $this->links($clock),
            $this->contents->roots,
            $this->published,
            new ContentNormalizer([new ExcerptNormalizer()]),
            new TemplateResolver(['news' => 'news'], 'default'),
            new Environment(new ArrayLoader([
                'news' => 'news|{{ content.excerptTitle }}|{{ reference }}|{{ locale }}|{{ preview ? "preview" : "" }}|{{ left_language ? "left" : "stayed" }}',
                'default' => 'default|{{ reference }}',
            ])),
        );
    }

    /**
     * @param array<string, string> $urls
     *
     * @return array<string, string>
     */
    private function sorted(array $urls): array
    {
        ksort($urls);

        return $urls;
    }
}
