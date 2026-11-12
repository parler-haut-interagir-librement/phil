<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoutes;

/**
 * The pair "previous route, route" of a page view, in aggregate: a counter per pair and no thread between pairs, so
 * that no individual path through a site can be put back together.
 */
final readonly class RouteTransition
{
    public const string SEPARATOR = '>';

    /**
     * The counter value of the pair, or `null` when either side is not a declared route.
     */
    public static function valueOf(DeclaredRoutes $routes, string $route, string $previousPath): ?string
    {
        $previous = $routes->normalize($previousPath);

        if (CounterKey::OTHER === $previous || CounterKey::OTHER === $route) {
            return null;
        }

        $value = $previous.self::SEPARATOR.$route;

        return \strlen($value) > CounterKey::MAX_VALUE_LENGTH ? null : $value;
    }
}
