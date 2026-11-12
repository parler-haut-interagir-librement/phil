<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Repository;

use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;

/**
 * Finds and stores routes. `Infrastructure\Doctrine\DoctrineRouteRepository` is the Doctrine adapter.
 */
interface RouteRepository
{
    /**
     * The route, current or history, that holds `$slug` for this kind of content in `$locale`.
     */
    public function findBySlug(string $resourceKey, Locale $locale, Slug $slug): ?Route;

    /**
     * The current route of `$content` in `$locale`; history routes are never returned.
     */
    public function findCurrent(ContentReference $content, Locale $locale): ?Route;

    /**
     * The current routes in `$locale` of the contents of one kind named by `$resourceIds`, in one lookup: a
     * content without one is missing from the list, which follows no order.
     *
     * @param list<string> $resourceIds
     *
     * @return list<Route>
     */
    public function findCurrentAmong(string $resourceKey, Locale $locale, array $resourceIds): array;

    /**
     * Every route of `$content`, current and history, in every locale.
     *
     * @return list<Route>
     */
    public function findAllOf(ContentReference $content): array;

    public function add(Route $route): void;

    public function remove(Route $route): void;
}
