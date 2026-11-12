<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Routing;

use Phil\ContentBundle\Domain\Repository\RouteRepository;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\LocaleDimensions\FallbackChain;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;

/**
 * Finds what a requested slug addresses, for one kind of content.
 *
 * The slug is looked up in each locale of the chain, in order: a page requested in `fr_CA` that only exists
 * in `fr` is found by its `fr` slug. A history route answers with the current route of the same root and
 * locale, to redirect to; a history route whose root has no current route any more is not found.
 */
final readonly class RouteResolver
{
    public function __construct(
        private RouteRepository $routes,
    ) {
    }

    public function resolve(string $resourceKey, FallbackChain $chain, string $slug): ?RouteMatch
    {
        try {
            $slug = Slug::fromString($slug);
        } catch (InvalidSlug) {
            return null;
        }

        foreach ($chain->locales as $locale) {
            $route = $this->routes->findBySlug($resourceKey, $locale, $slug);

            if (!$route instanceof Route) {
                continue;
            }

            if (!$route->isHistory()) {
                return RouteMatch::serve($route);
            }

            $current = $this->routes->findCurrent($route->content(), $locale);

            return $current instanceof Route ? RouteMatch::redirectTo($current) : null;
        }

        return null;
    }
}
