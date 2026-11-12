<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The closed list of routes a site counts, and the normalization of a received path into one of them.
 *
 * **A path that matches no declared route is counted under {@see CounterKey::OTHER} at ingestion.** A forged path
 * therefore creates no counter, and a segment such as a user name is never kept.
 */
final readonly class DeclaredRoutes
{
    /**
     * @param list<DeclaredRoute>                       $routes
     * @param array<int, non-empty-list<DeclaredRoute>> $byLength the same routes, in their order, under the number of
     *                                                            segments of the paths they match
     */
    private function __construct(
        public array $routes,
        private array $byLength,
    ) {
    }

    /**
     * @param iterable<DeclaredRoute> $routes
     */
    public static function of(iterable $routes): self
    {
        $list = [];
        $byLength = [];

        foreach ($routes as $route) {
            foreach ($list as $declared) {
                if ($declared->name === $route->name) {
                    throw InvalidValue::because(\sprintf('The route "%s" is declared twice.', $route->name));
                }

                if ($declared->hasSameShapeAs($route)) {
                    throw InvalidValue::because(\sprintf('The routes "%s" and "%s" match exactly the same paths.', $declared->name, $route->name));
                }
            }

            $list[] = $route;
            $byLength[$route->length()][] = $route;
        }

        return new self($list, $byLength);
    }

    public static function none(): self
    {
        return new self([], []);
    }

    /**
     * The name of the declared route a path matches, or {@see CounterKey::OTHER}.
     *
     * The query string and the fragment are dropped first, should a client send them: they can carry an
     * authorization code, a reset token or an unsubscribe token, and none of them is ever kept. When several
     * routes match, the one with the most literal segments wins, then the first declared. Only the routes with as many
     * segments as the path are tried.
     */
    public function normalize(string $path): string
    {
        $path = substr($path, 0, strcspn($path, '?#'));

        if (!str_starts_with($path, '/')) {
            return CounterKey::OTHER;
        }

        $segments = DeclaredRoute::split($path);

        if (\in_array('', $segments, true)) {
            return CounterKey::OTHER;
        }

        $best = null;

        foreach ($this->byLength[\count($segments)] ?? [] as $route) {
            if ($route->matches($segments) && (null === $best || $route->specificity() > $best->specificity())) {
                $best = $route;
            }
        }

        if (null === $best) {
            return CounterKey::OTHER;
        }

        return $best->name;
    }
}
