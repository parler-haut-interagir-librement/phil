<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoute;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoutes;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclaredRoutes::class)]
#[CoversClass(DeclaredRoute::class)]
final class DeclaredRoutesTest extends TestCase
{
    private DeclaredRoutes $routes;

    protected function setUp(): void
    {
        $this->routes = DeclaredRoutes::of([
            DeclaredRoute::of('home', '/'),
            DeclaredRoute::of('event', '/events/{slug}'),
            DeclaredRoute::of('events.archive', '/events/archive'),
            DeclaredRoute::of('profile', '/u/{name}'),
        ]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paths(): iterable
    {
        yield 'root' => ['/', 'home'];
        yield 'placeholder' => ['/events/spring-con', 'event'];
        yield 'trailing slash' => ['/events/spring-con/', 'event'];
        yield 'literal beats placeholder' => ['/events/archive', 'events.archive'];
        yield 'query dropped' => ['/events/spring-con?code=abc&state=xyz', 'event'];
        yield 'fragment dropped' => ['/u/alice#token', 'profile'];
        yield 'query alone' => ['/?reset=token', 'home'];
        yield 'undeclared' => ['/admin', CounterKey::OTHER];
        yield 'too deep' => ['/events/spring-con/tickets', CounterKey::OTHER];
        yield 'literal is case-sensitive' => ['/Events/archive', CounterKey::OTHER];
        yield 'empty segment' => ['/events//x', CounterKey::OTHER];
        yield 'not a path' => ['events/x', CounterKey::OTHER];
        yield 'empty' => ['', CounterKey::OTHER];
    }

    public function testAPlaceholderMayStandBeforeALiteral(): void
    {
        $route = DeclaredRoute::of('edit', '/{id}/edit');

        self::assertTrue($route->matches(['1', 'edit']));
        self::assertFalse($route->matches(['1', 'view']));
    }

    public function testARouteKnowsItsLengthAndItsLiterals(): void
    {
        $route = DeclaredRoute::of('edit', '/a/{id}/edit');

        self::assertSame(3, $route->length());
        self::assertSame(2, $route->specificity());
        self::assertSame(0, DeclaredRoute::of('home', '/')->length());
        self::assertFalse($route->matches(['a', '1']));
        self::assertFalse($route->matches(['a', '1', 'edit', 'x']));
    }

    /**
     * Routes of several lengths, declared in any order: each path is tried against the routes of its own length.
     */
    public function testRoutesOfSeveralLengthsEachMatchTheirOwnPaths(): void
    {
        $routes = DeclaredRoutes::of([
            DeclaredRoute::of('deep', '/{a}/{b}/{c}'),
            DeclaredRoute::of('one', '/{a}'),
            DeclaredRoute::of('two', '/x/{b}'),
            DeclaredRoute::of('two.any', '/{a}/{b}'),
        ]);

        self::assertSame('deep', $routes->normalize('/x/y/z'));
        self::assertSame('one', $routes->normalize('/x'));
        self::assertSame('two', $routes->normalize('/x/y'));
        self::assertSame('two.any', $routes->normalize('/y/y'));
        self::assertSame(CounterKey::OTHER, $routes->normalize('/'));
        self::assertSame(CounterKey::OTHER, $routes->normalize('/a/b/c/d'));
    }

    #[DataProvider('paths')]
    public function testAPathIsNormalizedToADeclaredRouteOrOther(string $path, string $expected): void
    {
        self::assertSame($expected, $this->routes->normalize($path));
    }

    /**
     * An empty segment is refused before matching, even where a placeholder would take it.
     */
    public function testAnEmptySegmentNeverFillsAPlaceholder(): void
    {
        $routes = DeclaredRoutes::of([DeclaredRoute::of('item', '/a/{id}/b')]);

        self::assertSame('item', $routes->normalize('/a/1/b'));
        self::assertSame(CounterKey::OTHER, $routes->normalize('/a//b'));
    }

    /**
     * Two routes as specific as each other: the first declared wins.
     */
    public function testOnATieTheFirstDeclaredRouteWins(): void
    {
        $routes = DeclaredRoutes::of([DeclaredRoute::of('first', '/a/{x}'), DeclaredRoute::of('second', '/{y}/b')]);

        self::assertSame('first', $routes->normalize('/a/b'));
    }

    /**
     * The segment a placeholder matched is never part of what is counted.
     */
    public function testAPlaceholderSegmentIsNeverKept(): void
    {
        self::assertSame('profile', $this->routes->normalize('/u/alice'));
        self::assertSame('profile', $this->routes->normalize('/u/bob'));
    }

    public function testNoDeclaredRouteCountsEverythingAsOther(): void
    {
        self::assertSame(CounterKey::OTHER, DeclaredRoutes::none()->normalize('/'));
    }

    public function testARouteNameIsDeclaredOnce(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The route "home" is declared twice.');

        DeclaredRoutes::of([DeclaredRoute::of('home', '/'), DeclaredRoute::of('home', '/start')]);
    }

    public function testTwoRoutesOfTheSameShapeAreRefused(): void
    {
        $this->expectException(InvalidValue::class);

        DeclaredRoutes::of([DeclaredRoute::of('a', '/x/{id}'), DeclaredRoute::of('b', '/x/{slug}')]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRoutes(): iterable
    {
        yield 'name is other' => [CounterKey::OTHER, '/'];
        yield 'name with a capital' => ['Home', '/'];
        yield 'relative pattern' => ['home', 'home'];
        yield 'partial placeholder' => ['event', '/events/x{slug}'];
        yield 'wildcard' => ['event', '/events/*/x?'];
        yield 'text after a placeholder' => ['item', '/{id}x'];
        yield 'an unclosed placeholder' => ['item', '/{id'];
        yield 'a literal with a trailing newline' => ['item', "/items\n"];
        yield 'a placeholder with a trailing newline' => ['item', "/items/{id}\n"];
    }

    #[DataProvider('invalidRoutes')]
    public function testAnInvalidRouteIsRefused(string $name, string $pattern): void
    {
        $this->expectException(InvalidValue::class);

        DeclaredRoute::of($name, $pattern);
    }
}
