<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Twig;

/**
 * The names the recipes of the kit are installed under, in the subdirectory of the application's components
 * directory: with `ui`, `Button` is `ui:Button`. The recipes have the same names in the PHIL UX kit and in the Shadcn
 * kit of the Symfony UX Toolkit, so that the templates draw either.
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
