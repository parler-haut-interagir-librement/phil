<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use InvalidArgumentException;

/**
 * Which Symfony routes serve each kind of content (`phil_content.routes`): the routes the application mounts
 * `ContentController` on.
 *
 * ```yaml
 * phil_content:
 *     routes:
 *         news: news_show                  # news_show: { path: /news/{slug}, controller: ...ContentController }
 *         static_page: [page_show, privacy] # privacy: { path: /privacy, defaults: { slug: privacy }, ... }
 * ```
 *
 * **The first route of a kind is the one its URLs are generated on**, and it takes a `{slug}` parameter. The
 * routes after it serve the same kind under addresses of their own, usually one page each, with the slug as
 * a route default: a page keeps the route name its templates already link to, and every one of its URLs is
 * still generated on the first route. The controller reads the resource key from whichever route matched.
 *
 * A route serves one kind of content only: a route listed under two keys is refused.
 */
final readonly class ResourceRoutes
{
    /**
     * @var array<string, non-empty-list<string>> resource key => route names, the generating route first
     */
    private array $routes;

    /**
     * @param array<string, string|array<array-key, string>> $routes resource key => route name, or route names
     *                                                               with the generating route first
     *
     * @throws InvalidArgumentException when a kind has no route, or a route serves two kinds
     */
    public function __construct(array $routes)
    {
        $normalized = [];
        $owners = [];

        foreach ($routes as $resourceKey => $names) {
            $names = \is_string($names) ? [$names] : array_values($names);

            if ([] === $names) {
                throw new InvalidArgumentException(\sprintf('The kind of content "%s" is mounted on no route.', $resourceKey));
            }

            foreach ($names as $name) {
                if (isset($owners[$name])) {
                    throw new InvalidArgumentException(\sprintf('The route "%s" serves both "%s" and "%s"; a route serves one kind of content.', $name, $owners[$name], $resourceKey));
                }

                $owners[$name] = $resourceKey;
            }

            $normalized[$resourceKey] = $names;
        }

        $this->routes = $normalized;
    }

    /**
     * The mapping as the bundle's configuration holds it, checked before the container is built.
     *
     * @param array<array-key, mixed> $routes
     *
     * @throws InvalidArgumentException when a key or a route name is not a string, a kind has no route, or a
     *                                  route serves two kinds
     */
    public static function fromConfiguration(array $routes): self
    {
        $checked = [];

        foreach ($routes as $resourceKey => $names) {
            if (!\is_string($resourceKey)) {
                throw new InvalidArgumentException(\sprintf('The kind of content %d is not a resource key.', $resourceKey));
            }

            $names = \is_array($names) ? $names : [$names];

            foreach ($names as $name) {
                if (!\is_string($name)) {
                    throw new InvalidArgumentException(\sprintf('The routes of "%s" are route names.', $resourceKey));
                }
            }

            /** @var array<array-key, string> $names */
            $checked[$resourceKey] = $names;
        }

        return new self($checked);
    }

    /**
     * The route the URLs of `$resourceKey` are generated on: the first one it is mounted on.
     */
    public function routeOf(string $resourceKey): ?string
    {
        return $this->routes[$resourceKey][0] ?? null;
    }

    /**
     * @return list<string> the kinds of content mounted on routes, in the order of the configuration
     */
    public function resourceKeys(): array
    {
        return array_keys($this->routes);
    }

    public function resourceKeyOf(string $route): ?string
    {
        foreach ($this->routes as $resourceKey => $names) {
            if (\in_array($route, $names, true)) {
                return $resourceKey;
            }
        }

        return null;
    }
}
