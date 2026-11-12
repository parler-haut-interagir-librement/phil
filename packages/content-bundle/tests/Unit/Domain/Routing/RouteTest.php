<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Routing;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\Routing\RouteId;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Route::class)]
final class RouteTest extends TestCase
{
    public function testANewRouteIsTheCurrentAddressOfItsContent(): void
    {
        $route = Route::current(ContentReference::of('news', '42'), Locale::fromString('fr_CA'), Slug::fromString('ouverture'));

        self::assertInstanceOf(RouteId::class, $route->id());
        self::assertTrue($route->content()->equals(ContentReference::of('news', '42')));
        self::assertSame('fr_CA', $route->locale()->value);
        self::assertSame('ouverture', $route->slug()->value);
        self::assertFalse($route->isHistory());
    }

    public function testEachRouteHasItsOwnIdentifier(): void
    {
        $content = ContentReference::of('news', '42');
        $locale = Locale::fromString('fr');

        self::assertFalse(Route::current($content, $locale, Slug::fromString('a'))->id()->equals(Route::current($content, $locale, Slug::fromString('a'))->id()));
    }

    public function testARetiredRouteIsHistory(): void
    {
        $route = Route::current(ContentReference::of('news', '42'), Locale::fromString('fr'), Slug::fromString('ouverture'));

        $route->retire();

        self::assertTrue($route->isHistory());
    }

    public function testPointingARouteRevivesItForTheNewContent(): void
    {
        $route = Route::current(ContentReference::of('news', '42'), Locale::fromString('fr'), Slug::fromString('ouverture'));
        $route->retire();

        $route->pointTo(ContentReference::of('news', '43'));

        self::assertFalse($route->isHistory());
        self::assertSame('news:43', $route->content()->toString());
        self::assertSame('ouverture', $route->slug()->value);
    }

    public function testARouteNeverPointsToAnotherKindOfContent(): void
    {
        $route = Route::current(ContentReference::of('news', '42'), Locale::fromString('fr'), Slug::fromString('ouverture'));
        $route->retire();

        try {
            $route->pointTo(ContentReference::of('static_page', '42'));
            self::fail('A route of another kind was accepted.');
        } catch (InvalidContent $invalidContent) {
            self::assertSame('A route of "news" cannot point to a content of "static_page".', $invalidContent->getMessage());
        }

        self::assertTrue($route->isHistory());
        self::assertSame('news:42', $route->content()->toString());
    }
}
