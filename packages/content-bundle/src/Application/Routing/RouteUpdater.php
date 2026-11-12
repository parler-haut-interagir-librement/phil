<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Routing;

use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Exception\SlugTaken;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\RouteRepository;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;

/**
 * Keeps the route table in step with what is published: the current route of a root in a locale, and the
 * history routes its old slugs leave behind.
 *
 * A route whose root no longer exists — deleted by the application around `ContentManager::delete()`, which
 * removes the routes with the root — holds its slug for nobody: it is free, and taken over like a history
 * route.
 */
final readonly class RouteUpdater
{
    public function __construct(
        private RouteRepository $routes,
        private ContentRootRepository $roots,
    ) {
    }

    /**
     * Whether `$content` may take `$slug` in `$locale`: free, its own, a history route of another root, which
     * gives it up, or the route of a root that no longer exists.
     *
     * @throws SlugTaken
     */
    public function assertAvailable(ContentReference $content, Locale $locale, Slug $slug): void
    {
        $route = $this->routes->findBySlug($content->resourceKey, $locale, $slug);

        if (!$route instanceof Route || $route->isHistory() || $route->content()->equals($content)) {
            return;
        }

        if ($this->roots->find($route->content()) instanceof ContentRoot) {
            throw SlugTaken::by($route->content(), $locale, $slug);
        }
    }

    /**
     * Makes `$slug` the current route of `$content` in `$locale`; the previous current route, if any, becomes
     * history and redirects.
     *
     * @throws SlugTaken
     */
    public function assign(ContentReference $content, Locale $locale, Slug $slug): void
    {
        $current = $this->routes->findCurrent($content, $locale);

        if ($current instanceof Route && $current->slug()->equals($slug)) {
            return;
        }

        $this->assertAvailable($content, $locale, $slug);
        $existing = $this->routes->findBySlug($content->resourceKey, $locale, $slug);

        if ($existing instanceof Route) {
            $existing->pointTo($content);
        } else {
            $this->routes->add(Route::current($content, $locale, $slug));
        }

        $current?->retire();
    }
}
