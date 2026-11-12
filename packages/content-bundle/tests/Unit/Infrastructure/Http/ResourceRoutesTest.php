<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Http;

use InvalidArgumentException;
use Phil\ContentBundle\Infrastructure\Http\ResourceRoutes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceRoutes::class)]
final class ResourceRoutesTest extends TestCase
{
    public function testTheMappingReadsBothWays(): void
    {
        $routes = new ResourceRoutes(['news' => 'news_show', 'static_page' => 'page_show']);

        self::assertSame('page_show', $routes->routeOf('static_page'));
        self::assertSame('static_page', $routes->resourceKeyOf('page_show'));
        self::assertNull($routes->routeOf('event'));
        self::assertNull($routes->resourceKeyOf('home'));
    }

    public function testAKindMountedOnSeveralRoutesGeneratesOnTheFirst(): void
    {
        $routes = new ResourceRoutes(['news' => ['news_show'], 'static_page' => ['page_show', 'privacy', 'about']]);

        self::assertSame('page_show', $routes->routeOf('static_page'));
        self::assertSame('news_show', $routes->routeOf('news'));
        self::assertSame('static_page', $routes->resourceKeyOf('page_show'));
        self::assertSame('static_page', $routes->resourceKeyOf('privacy'));
        self::assertSame('static_page', $routes->resourceKeyOf('about'));
        self::assertSame('news', $routes->resourceKeyOf('news_show'));
    }

    public function testTheRoutesOfAKindAreReadInOrderWhateverTheirKeys(): void
    {
        $routes = new ResourceRoutes(['static_page' => [3 => 'page_show', 1 => 'privacy']]);

        self::assertSame('page_show', $routes->routeOf('static_page'));
        self::assertSame('static_page', $routes->resourceKeyOf('privacy'));
    }

    public function testTheConfigurationIsReadAsTheConstructorReadsIt(): void
    {
        $routes = ResourceRoutes::fromConfiguration(['news' => 'news_show', 'static_page' => ['page_show', 'about']]);

        self::assertSame('news_show', $routes->routeOf('news'));
        self::assertSame('page_show', $routes->routeOf('static_page'));
        self::assertSame('static_page', $routes->resourceKeyOf('about'));
    }

    public function testAConfiguredRouteThatIsNotANameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The routes of "static_page" are route names.');

        ResourceRoutes::fromConfiguration(['static_page' => ['page_show', 12]]);
    }

    public function testAKeyThatIsNotAResourceKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The kind of content 0 is not a resource key.');

        ResourceRoutes::fromConfiguration(['page_show']);
    }

    public function testAConfiguredKindIsCheckedLikeAnyOther(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The route "page_show" serves both "news" and "static_page"');

        ResourceRoutes::fromConfiguration(['news' => 'page_show', 'static_page' => 'page_show']);
    }

    public function testAKindWithoutRouteIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The kind of content "static_page" is mounted on no route.');

        new ResourceRoutes(['static_page' => []]);
    }

    public function testARouteServingTwoKindsIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The route "page_show" serves both "news" and "static_page"; a route serves one kind of content.');

        new ResourceRoutes(['news' => 'page_show', 'static_page' => ['about', 'page_show']]);
    }

    public function testARouteListedTwiceForOneKindIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The route "about" serves both "static_page" and "static_page"');

        new ResourceRoutes(['static_page' => ['page_show', 'about', 'about']]);
    }
}
