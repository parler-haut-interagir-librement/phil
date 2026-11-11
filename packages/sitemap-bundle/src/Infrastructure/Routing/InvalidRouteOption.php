<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Infrastructure\Routing;

use InvalidArgumentException;

/**
 * The `sitemap` option of a route that cannot make a page.
 */
final class InvalidRouteOption extends InvalidArgumentException
{
    public static function of(string $route, string $reason): self
    {
        return new self(\sprintf('The sitemap option of the route "%s" %s', $route, $reason));
    }
}
