<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Measure;

use Phil\AnalyticsBundle\Domain\Measure\RouteTransition;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoute;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoutes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteTransition::class)]
final class RouteTransitionTest extends TestCase
{
    public function testThePairOfDeclaredRoutes(): void
    {
        $routes = DeclaredRoutes::of([DeclaredRoute::of('home', '/'), DeclaredRoute::of('event', '/events/{slug}')]);

        self::assertSame('home>event', RouteTransition::valueOf($routes, 'event', '/?x=1'));
        self::assertSame('event>event', RouteTransition::valueOf($routes, 'event', '/events/a'));
    }

    public function testASideThatIsNotDeclaredCountsNothing(): void
    {
        $routes = DeclaredRoutes::of([DeclaredRoute::of('home', '/')]);

        self::assertNull(RouteTransition::valueOf($routes, 'home', '/unknown'));
        self::assertNull(RouteTransition::valueOf($routes, CounterKey::OTHER, '/'));
        self::assertNull(RouteTransition::valueOf($routes, 'home', 'not a path'));
    }

    public function testAPairTooLongForACounterCountsNothing(): void
    {
        $long = str_repeat('a', 64);
        $routes = DeclaredRoutes::of([DeclaredRoute::of($long, '/'), DeclaredRoute::of(substr($long, 1), '/b')]);

        self::assertNull(RouteTransition::valueOf($routes, $long, '/'));
        self::assertSame(str_repeat('a', 63).'>'.$long, RouteTransition::valueOf($routes, $long, '/b'));
    }
}
