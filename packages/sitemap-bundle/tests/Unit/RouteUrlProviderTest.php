<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Sitemap\Alternate;
use Phil\Sitemap\ChangeFrequency;
use Phil\Sitemap\Sitemap;
use Phil\Sitemap\Url;
use Phil\SitemapBundle\Infrastructure\PopulateSitemap;
use Phil\SitemapBundle\Infrastructure\Routing\InvalidRouteOption;
use Phil\SitemapBundle\Infrastructure\Routing\RouteUrlProvider;
use Phil\SitemapBundle\Infrastructure\Sitemaps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

#[CoversClass(RouteUrlProvider::class)]
#[CoversClass(InvalidRouteOption::class)]
#[CoversClass(Sitemaps::class)]
#[CoversClass(PopulateSitemap::class)]
final class RouteUrlProviderTest extends TestCase
{
    public function testRoutesWithTheOptionArePages(): void
    {
        $sitemap = $this->sitemap([
            'home' => new Route('/', options: ['sitemap' => true]),
            'page' => new Route('/page/{number}', ['number' => 1], options: ['sitemap' => ['priority' => 1, 'changefreq' => 'never', 'lastmod' => 'now', 'section' => 'pages']]),
            'dated' => new Route('/dated', options: ['sitemap' => ['lastmod' => '2026-11-01 08:00']]),
            'about.fr' => new Route('/a-propos', ['_locale' => 'fr', '_canonical_route' => 'about'], options: ['sitemap' => ['section' => 'pages']]),
            'about.en_CA' => new Route('/about', ['_locale' => 'en_CA', '_canonical_route' => 'about'], options: ['sitemap' => ['section' => 'pages']]),
            'alone.fr' => new Route('/seul', ['_locale' => 'fr', '_canonical_route' => 'alone'], options: ['sitemap' => true]),
            'other' => new Route('/other', ['_locale' => 'fr'], options: ['sitemap' => true]),
            'hidden' => new Route('/hidden', options: ['sitemap' => false]),
            'plain' => new Route('/plain'),
        ], defaults: ['priority' => 0.3, 'changefreq' => 'monthly', 'lastmod' => null]);

        $default = $sitemap->sections()['default'];
        self::assertSame(['https://example.com/', 'https://example.com/dated', 'https://example.com/seul', 'https://example.com/other'], array_map(static fn (Url $url): string => $url->loc, $default));
        self::assertSame([0.3, ChangeFrequency::Monthly, null, []], [$default[0]->priority, $default[0]->changefreq, $default[0]->lastmod, $default[0]->alternates]);
        self::assertSame('2026-11-01T08:00:00-05:00', $default[1]->lastmod?->format(\DATE_ATOM));
        self::assertSame([[], []], [$default[2]->alternates, $default[3]->alternates]);

        [$page, $french, $english] = $sitemap->sections()['pages'];
        self::assertSame(['https://example.com/page', 1.0, ChangeFrequency::Never, '2026-11-04T08:00:00-05:00'], [$page->loc, $page->priority, $page->changefreq, $page->lastmod?->format(\DATE_ATOM)]);
        self::assertSame(['https://example.com/a-propos', 'https://example.com/about'], [$french->loc, $english->loc]);
        self::assertSame([['fr', 'https://example.com/a-propos'], ['en-CA', 'https://example.com/about']], array_map(static fn (Alternate $alternate): array => [$alternate->hreflang, $alternate->href], $english->alternates));
        self::assertEquals($french->alternates, $english->alternates);
        self::assertSame(['default', 'pages'], array_keys($sitemap->sections()));
    }

    public function testARouterOnLocalhostMakesPagesOnlyWhenAllowed(): void
    {
        $routes = ['home' => new Route('/', options: ['sitemap' => true]), 'about.fr' => new Route('/a-propos', ['_locale' => 'fr', '_canonical_route' => 'about'], options: ['sitemap' => true]), 'about.en' => new Route('/about', ['_locale' => 'en', '_canonical_route' => 'about'], options: ['sitemap' => true])];

        try {
            $this->sitemap($routes, host: 'localhost');
            self::fail('A page on localhost was made.');
        } catch (InvalidRouteOption $invalidRouteOption) {
            self::assertStringContainsString('"http://localhost/" is not.', $invalidRouteOption->getMessage());
        }

        $pages = $this->sitemap($routes, host: 'localhost', allowLocalhost: true)->sections()['default'];
        self::assertSame(['http://localhost/', 'http://localhost/a-propos', 'http://localhost/about'], array_map(static fn (Url $url): string => $url->loc, $pages));
        self::assertSame('http://localhost/about', $pages[1]->alternates[1]->href);
    }

    public function testOneSectionLeavesTheOthersAndTheEventIsDispatched(): void
    {
        $dispatcher = new EventDispatcher();
        $heard = [];
        $dispatcher->addListener(PopulateSitemap::class, static function (PopulateSitemap $event) use (&$heard): void {
            $heard[] = $event->section;
            $event->urls->add(new Url('https://example.com/listened'), 'pages');
        });
        $routes = ['first' => new Route('/first'), 'home' => new Route('/', options: ['sitemap' => true]), 'other' => new Route('/other', options: ['sitemap' => ['section' => 'other']]), 'page' => new Route('/page', options: ['sitemap' => ['section' => 'pages']])];

        $sitemap = $this->sitemap($routes, 'pages', $dispatcher);

        self::assertSame(['pages'], array_keys($sitemap->sections()));
        self::assertSame(['https://example.com/page', 'https://example.com/listened'], array_map(static fn (Url $url): string => $url->loc, $sitemap->sections()['pages']));
        self::assertSame(['pages'], $heard);
        self::assertSame(['main'], array_keys($this->sitemap(['home' => new Route('/', options: ['sitemap' => true])], defaultSection: 'main')->sections()));
        self::assertSame([], new Sitemaps([])->build()->sections());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badOptions(): iterable
    {
        $route = 'The sitemap option of the route "bad" ';

        yield 'a word' => [['sitemap' => 'yes'], $route.'is true, false, or a map of priority, changefreq, lastmod and section.'];
        yield 'an unknown key' => [['sitemap' => ['priority' => 0.5, 'weight' => 1, 'color' => 'red']], $route.'knows priority, changefreq, lastmod and section, not weight, color.'];
        yield 'a word as priority' => [['sitemap' => ['priority' => 'high']], $route.'takes a number as priority, and words as changefreq, lastmod and section.'];
        yield 'a number as changefreq' => [['sitemap' => ['changefreq' => 7]], $route.'takes a number as priority'];
        yield 'a number as lastmod' => [['sitemap' => ['lastmod' => 2026]], $route.'takes a number as priority'];
        yield 'a number as section' => [['sitemap' => ['section' => 1]], $route.'takes a number as priority'];
        yield 'an unknown changefreq' => [['sitemap' => ['changefreq' => 'often']], $route.'takes a changefreq of always, hourly, daily, weekly, monthly, yearly or never, not "often".'];
        yield 'a bad lastmod' => [['sitemap' => ['lastmod' => 'someday']], $route.'takes "now" or a date as lastmod, not "someday".'];
        yield 'a priority too high' => [['sitemap' => ['priority' => 1.5]], $route.'makes no page: A priority is 0.0 to 1.0, in steps of 0.1; 1.5 is not.'];
    }

    #[DataProvider('badOptions')]
    public function testABadOptionIsRefused(mixed $options, string $message): void
    {
        $this->expectException(InvalidRouteOption::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->sitemap(['bad' => new Route('/bad', options: \is_array($options) ? $options : [])]);
    }

    public function testARouteWithParametersNeedsAProvider(): void
    {
        $this->expectException(InvalidRouteOption::class);
        $this->expectExceptionMessageIsOrContains('The sitemap option of the route "show" needs slug, page to make a URL: give the pages of this route with a UrlProvider.');

        $this->sitemap(['show' => new Route('/{slug}/{page}/{size}', ['size' => 10], options: ['sitemap' => true])]);
    }

    /**
     * @param array<string, Route>                                           $routes
     * @param array{priority: ?float, changefreq: ?string, lastmod: ?string} $defaults
     */
    private function sitemap(array $routes, ?string $section = null, ?EventDispatcher $dispatcher = null, string $defaultSection = 'default', array $defaults = ['priority' => null, 'changefreq' => null, 'lastmod' => null], string $host = 'example.com', bool $allowLocalhost = false): Sitemap
    {
        $collection = new RouteCollection();

        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        $router = new Router(new ClosureLoader(), static fn (): RouteCollection => $collection, [], new RequestContext(host: $host, scheme: 'example.com' === $host ? 'https' : 'http'));
        $clock = new MockClock(new DateTimeImmutable('2026-11-04 08:00', new DateTimeZone('America/Toronto')));
        $provider = $allowLocalhost ? new RouteUrlProvider($router, $clock, $defaultSection, $defaults, true) : new RouteUrlProvider($router, $clock, $defaultSection, $defaults);

        return new Sitemaps([$provider], $dispatcher)->build($section);
    }
}
