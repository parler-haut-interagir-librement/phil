<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Routing;

use Override;
use Phil\ContentBundle\Application\Routing\RouteMatch;
use Phil\ContentBundle\Application\Routing\RouteResolver;
use Phil\ContentBundle\Application\Routing\RouteUpdater;
use Phil\ContentBundle\Domain\Exception\SlugTaken;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Tests\Unit\Fixture\ExistingRoots;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryRouteRepository;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteResolver::class)]
#[CoversClass(RouteMatch::class)]
#[CoversClass(RouteUpdater::class)]
final class RouteResolverTest extends TestCase
{
    private InMemoryRouteRepository $routes;

    private ExistingRoots $roots;

    private RouteUpdater $updater;

    private RouteResolver $resolver;

    #[Override]
    protected function setUp(): void
    {
        $this->routes = new InMemoryRouteRepository();
        $this->roots = new ExistingRoots();
        $this->updater = new RouteUpdater($this->routes, $this->roots);
        $this->resolver = new RouteResolver($this->routes);
    }

    public function testACurrentSlugIsServed(): void
    {
        $this->updater->assign(ContentReference::of('news', '1'), Locale::fromString('fr'), Slug::fromString('ouverture'));

        $match = $this->resolver->resolve('news', $this->chain('fr'), 'ouverture');

        self::assertInstanceOf(RouteMatch::class, $match);
        self::assertFalse($match->redirect);
        self::assertSame('news:1', $match->route->content()->toString());
    }

    public function testAnOldSlugRedirectsToTheCurrentOne(): void
    {
        $news = ContentReference::of('news', '1');
        $this->updater->assign($news, Locale::fromString('fr'), Slug::fromString('ouverture'));
        $this->updater->assign($news, Locale::fromString('fr'), Slug::fromString('grande-ouverture'));
        $this->updater->assign($news, Locale::fromString('fr'), Slug::fromString('tres-grande-ouverture'));

        $match = $this->resolver->resolve('news', $this->chain('fr'), 'ouverture');

        self::assertInstanceOf(RouteMatch::class, $match);
        self::assertTrue($match->redirect);
        self::assertSame('tres-grande-ouverture', $match->route->slug()->value, 'A chain of renames redirects once.');
    }

    public function testAssigningTheCurrentSlugAgainChangesNothing(): void
    {
        $news = ContentReference::of('news', '1');
        $this->updater->assign($news, Locale::fromString('fr'), Slug::fromString('ouverture'));
        $this->updater->assign($news, Locale::fromString('fr'), Slug::fromString('ouverture'));

        self::assertSame(['fr/ouverture → news:1'], $this->routes->describe());
    }

    public function testAHistorySlugWhoseRootHasNoCurrentRouteIsNotFound(): void
    {
        $news = ContentReference::of('news', '1');
        $this->updater->assign($news, Locale::fromString('fr'), Slug::fromString('ouverture'));
        $route = $this->routes->findCurrent($news, Locale::fromString('fr'));
        self::assertNotNull($route);
        $route->retire();

        self::assertNull($this->resolver->resolve('news', $this->chain('fr'), 'ouverture'));
    }

    public function testTheSlugIsLookedUpAlongTheChain(): void
    {
        $this->updater->assign(ContentReference::of('news', '1'), Locale::fromString('fr'), Slug::fromString('ouverture'));

        $match = $this->resolver->resolve('news', $this->chain('fr_CA', 'fr'), 'ouverture');

        self::assertSame('fr', $match?->route->locale()->value);
    }

    public function testTheFirstLocaleOfTheChainWins(): void
    {
        $this->updater->assign(ContentReference::of('news', '1'), Locale::fromString('fr'), Slug::fromString('ouverture'));
        $this->updater->assign(ContentReference::of('news', '2'), Locale::fromString('fr_CA'), Slug::fromString('ouverture'));

        self::assertSame('news:2', $this->resolver->resolve('news', $this->chain('fr_CA', 'fr'), 'ouverture')?->route->content()->toString());
    }

    public function testASlugOfAnotherKindOrLocaleIsNotFound(): void
    {
        $this->updater->assign(ContentReference::of('news', '1'), Locale::fromString('fr'), Slug::fromString('ouverture'));

        self::assertNull($this->resolver->resolve('static_page', $this->chain('fr'), 'ouverture'));
        self::assertNull($this->resolver->resolve('news', $this->chain('en'), 'ouverture'));
    }

    public function testAMalformedSlugIsNotFound(): void
    {
        self::assertNull($this->resolver->resolve('news', $this->chain('fr'), 'Not A Slug'));
    }

    public function testACurrentSlugOfAnotherRootIsRefused(): void
    {
        $this->updater->assign(ContentReference::of('news', '1'), Locale::fromString('fr'), Slug::fromString('ouverture'));

        $this->expectException(SlugTaken::class);

        $this->updater->assign(ContentReference::of('news', '2'), Locale::fromString('fr'), Slug::fromString('ouverture'));
    }

    public function testTheCurrentSlugOfARootThatNoLongerExistsIsTakenOver(): void
    {
        $this->updater->assign(ContentReference::of('news', '1'), Locale::fromString('fr'), Slug::fromString('ouverture'));
        $this->roots->deleted[] = 'news:1';

        $this->updater->assign(ContentReference::of('news', '2'), Locale::fromString('fr'), Slug::fromString('ouverture'));

        self::assertSame(['fr/ouverture → news:2'], $this->routes->describe());
    }

    private function chain(string ...$locales): FallbackChain
    {
        return FallbackChain::of(array_map(Locale::fromString(...), $locales));
    }
}
