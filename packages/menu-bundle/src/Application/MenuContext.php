<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Application;

/**
 * Where the visitor is: the route and its parameters, the path, and the language — what tells the current item.
 */
final readonly class MenuContext
{
    /**
     * @param array<array-key, mixed> $routeParameters
     */
    public function __construct(
        public ?string $route = null,
        public array $routeParameters = [],
        public ?string $path = null,
        public ?string $locale = null,
    ) {
    }
}
