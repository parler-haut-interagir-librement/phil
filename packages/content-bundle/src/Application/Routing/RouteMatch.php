<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Routing;

use Phil\ContentBundle\Domain\Routing\Route;

/**
 * What a slug resolved to: the current route to serve, or the current route an old slug redirects to.
 */
final readonly class RouteMatch
{
    private function __construct(
        public Route $route,
        public bool $redirect,
    ) {
    }

    public static function serve(Route $route): self
    {
        return new self($route, false);
    }

    public static function redirectTo(Route $route): self
    {
        return new self($route, true);
    }
}
