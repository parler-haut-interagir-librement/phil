<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Domain\Repository\RouteRepository;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;

final class InMemoryRouteRepository implements RouteRepository
{
    /**
     * @var list<Route>
     */
    private array $routes = [];

    public function findBySlug(string $resourceKey, Locale $locale, Slug $slug): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->content()->resourceKey === $resourceKey && $route->locale()->equals($locale) && $route->slug()->equals($slug)) {
                return $route;
            }
        }

        return null;
    }

    public function findCurrent(ContentReference $content, Locale $locale): ?Route
    {
        foreach ($this->routes as $route) {
            if (!$route->isHistory() && $route->content()->equals($content) && $route->locale()->equals($locale)) {
                return $route;
            }
        }

        return null;
    }

    public function findCurrentAmong(string $resourceKey, Locale $locale, array $resourceIds): array
    {
        return array_values(array_filter(
            $this->routes,
            static fn (Route $route): bool => !$route->isHistory() && $route->content()->resourceKey === $resourceKey && $route->locale()->equals($locale) && \in_array($route->content()->resourceId, $resourceIds, true),
        ));
    }

    public function findAllOf(ContentReference $content): array
    {
        return array_values(array_filter($this->routes, static fn (Route $route): bool => $route->content()->equals($content)));
    }

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    public function remove(Route $route): void
    {
        $this->routes = array_values(array_filter($this->routes, static fn (Route $kept): bool => $kept !== $route));
    }

    /**
     * Every route as `locale/slug → reference`, with `(history)` when it is one, sorted.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $lines = array_map(
            static fn (Route $route): string => \sprintf('%s/%s → %s%s', $route->locale()->value, $route->slug()->value, $route->content()->toString(), $route->isHistory() ? ' (history)' : ''),
            $this->routes,
        );
        sort($lines);

        return $lines;
    }
}
