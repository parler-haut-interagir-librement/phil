<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Twig;

/**
 * The names under which the recipes of the kit are installed, in the components subdirectory of the application's
 * `ux_toolkit` configuration: with `ui`, `Button` is `ui:Button`.
 */
final readonly class KitComponents
{
    private string $prefix;

    public function __construct(string $subdirectory = '')
    {
        $path = trim($subdirectory, '/');
        $this->prefix = '' === $path ? '' : str_replace('/', ':', $path).':';
    }

    public function name(string $recipe): string
    {
        return $this->prefix.$recipe;
    }
}
