<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\Sitemap\Sitemap;
use Phil\SitemapBundle\Infrastructure\Routing\RouteUrlProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

/**
 * The pages of the routes are found by reading the whole route collection, which a router loads again from its
 * resources in each request, then generating each page's URL: on every build of the sitemap.
 *
 * The routes are loaded by a closure, the cheapest loader; YAML files or attributes cost more. The generator is
 * compiled once into a cache directory, as in production.
 *
 * @BeforeMethods("setUp")
 *
 * @Revs(10)
 *
 * @Iterations(5)
 */
final class RouteUrlProviderBench
{
    private string $cacheDir = '';

    private int $pages = 0;

    private readonly MockClock $clock;

    private Router $router;

    public function __construct()
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-05 08:00'));
        $this->router = $this->router();
    }

    /**
     * @param array{pages: int} $params
     */
    public function setUp(array $params): void
    {
        $this->pages = $params['pages'];
        $this->cacheDir = sys_get_temp_dir().'/phil-sitemap-bundle-bench/'.hash('xxh128', __DIR__).'/'.$this->pages;
        $this->router = $this->router();
        $this->router->getRouteCollection();
        $this->router->getGenerator();
    }

    /**
     * A router as a new request has it: the route collection loaded again.
     *
     * @ParamProviders("sizes")
     */
    public function benchFreshRouter(): void
    {
        $this->provider($this->router())->provide(new Sitemap(), null);
    }

    /**
     * A router whose route collection is already loaded: the URLs alone.
     *
     * @ParamProviders("sizes")
     */
    public function benchLoadedRouter(): void
    {
        $this->provider($this->router)->provide(new Sitemap(), null);
    }

    /**
     * @return Generator<string, array{pages: int}>
     */
    public function sizes(): Generator
    {
        yield '20 pages, 60 routes' => ['pages' => 20];
        yield '200 pages, 600 routes' => ['pages' => 200];
        yield '1000 pages, 3000 routes' => ['pages' => 1_000];
    }

    private function provider(Router $router): RouteUrlProvider
    {
        return new RouteUrlProvider($router, $this->clock, 'default', ['priority' => 0.5, 'changefreq' => 'weekly', 'lastmod' => null]);
    }

    /**
     * Each page in French and in English, with the sitemap option, and a route with a parameter beside it, without.
     */
    private function router(): Router
    {
        $pages = $this->pages;

        return new Router(
            new ClosureLoader(),
            static function () use ($pages): RouteCollection {
                $routes = new RouteCollection();

                for ($index = 0; $index < $pages; ++$index) {
                    foreach (['fr' => '/fr/page-%d', 'en' => '/en/page-%d'] as $locale => $path) {
                        $routes->add(\sprintf('page_%d.%s', $index, $locale), new Route(\sprintf($path, $index), ['_locale' => $locale, '_canonical_route' => 'page_'.$index], options: ['sitemap' => 0 === $index % 2 ? true : ['priority' => 0.8, 'section' => 'pages']]));
                    }

                    $routes->add(\sprintf('item_%d', $index), new Route(\sprintf('/items-%d/{id}', $index), requirements: ['id' => '\d+']));
                }

                return $routes;
            },
            ['cache_dir' => $this->cacheDir],
            new RequestContext(host: 'example.com', scheme: 'https'),
        );
    }
}
