<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Benchmark;

use Generator;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\ContentBundle\Infrastructure\Http\ResourceRoutes;
use Phil\ContentBundle\Infrastructure\Sitemap\ContentUrlProvider;
use Phil\ContentBundle\Tests\Benchmark\Fixture\ContentDatabase;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\Sitemap\Sitemap;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The sitemap of the contents reads every published content of every locale, then the current route of each, and
 * generates an absolute URL with its alternates: its cost grows with the site, on each build of the sitemap.
 *
 * The entity manager is cleared before each build, as a new request starts with an empty identity map.
 *
 * @BeforeMethods("setUp")
 *
 * @Revs(3)
 *
 * @Iterations(5)
 */
final readonly class ContentUrlProviderBench
{
    private ContentDatabase $database;

    private ContentUrlProvider $provider;

    public function __construct()
    {
        $this->database = new ContentDatabase();
        $routes = new RouteCollection();
        $routes->add('article_show', new Route('/{_locale}/articles/{slug}'));

        $resourceRoutes = new ResourceRoutes(['article' => 'article_show']);

        $this->provider = new ContentUrlProvider(
            DeclaredLocales::fromArray(['fr' => [], 'en' => []]),
            $resourceRoutes,
            $this->database->published,
            new ContentUrlGenerator(
                $this->database->routes,
                $resourceRoutes,
                new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https')),
            ),
        );
    }

    /**
     * @param array{articles: int} $params
     */
    public function setUp(array $params): void
    {
        $this->database->store($params['articles']);
    }

    /**
     * Every article in French and in English, each with the other as its alternate.
     *
     * @ParamProviders("sizes")
     */
    public function benchProvide(): void
    {
        $this->database->entityManager->clear();
        $this->provider->provide(new Sitemap(), null);
    }

    /**
     * @return Generator<string, array{articles: int}>
     */
    public function sizes(): Generator
    {
        yield '10 articles' => ['articles' => 10];
        yield '100 articles' => ['articles' => 100];
        yield '1000 articles' => ['articles' => 1_000];
    }
}
