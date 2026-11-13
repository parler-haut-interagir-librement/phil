<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Twig;

/**
 * The names under which the recipes of the kit are installed, the application's components directory holding them in
 * the subdirectory of its `ux_toolkit` configuration: with `ui`, `Alert` is `ui:Alert`.
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
