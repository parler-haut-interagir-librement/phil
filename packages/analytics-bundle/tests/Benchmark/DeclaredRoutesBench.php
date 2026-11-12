<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Benchmark;

use Generator;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoute;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoutes;

/**
 * Every page view is normalized into one of the site's declared routes by a linear scan of them, so the cost grows
 * with the number of routes a site declares.
 */
final class DeclaredRoutesBench
{
    /**
     * @var array<int, DeclaredRoutes>
     */
    private array $routes = [];

    public function __construct()
    {
        foreach ([10, 100, 500] as $size) {
            $declared = [DeclaredRoute::of('home', '/'), DeclaredRoute::of('event', '/events/{slug}'), DeclaredRoute::of('events.archive', '/events/archive')];

            for ($index = \count($declared); $index < $size; ++$index) {
                $declared[] = 0 === $index % 2
                    ? DeclaredRoute::of(\sprintf('section-%d.show', $index), \sprintf('/section-%d/{slug}', $index))
                    : DeclaredRoute::of(\sprintf('section-%d.page', $index), \sprintf('/section-%d/{year}/{slug}', $index));
            }

            $this->routes[$size] = DeclaredRoutes::of($declared);
        }
    }

    /**
     * @ParamProviders({"routeCounts", "paths"})
     *
     * @param array{size: int, path: string} $params
     */
    public function benchNormalize(array $params): string
    {
        return $this->routes[$params['size']]->normalize($params['path']);
    }

    /**
     * @return Generator<string, array{size: int}>
     */
    public function routeCounts(): Generator
    {
        yield '10 routes' => ['size' => 10];
        yield '100 routes' => ['size' => 100];
        yield '500 routes' => ['size' => 500];
    }

    /**
     * @return Generator<string, array{path: string}>
     */
    public function paths(): Generator
    {
        yield 'two-segment match' => ['path' => '/events/archive?utm_source=newsletter'];
        yield 'unknown path' => ['path' => '/u/someone/settings'];
    }
}
