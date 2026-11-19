<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * What the templates of the library call:
 *
 * - `phil_library_kit()`, the name under which a recipe of the kit is installed;
 * - `phil_library_timezone()`, the library's time zone, in which the pages show the moments kept in UTC.
 */
final readonly class LibraryExtension
{
    public function __construct(
        private KitComponents $kit,
        private string $timezone = 'UTC',
    ) {
    }

    /**
     * The component of a recipe, as `{% component (phil_library_kit('Table:Row')) %}` calls it.
     */
    #[AsTwigFunction(name: 'phil_library_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[AsTwigFunction(name: 'phil_library_timezone')]
    public function timezone(): string
    {
        return $this->timezone;
    }
}
