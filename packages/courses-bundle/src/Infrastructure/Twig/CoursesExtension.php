<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * The function of the bundle's templates: `phil_courses_kit()`, the name under which a recipe of the kit is installed.
 */
final readonly class CoursesExtension
{
    public function __construct(
        private KitComponents $kit,
    ) {
    }

    /**
     * `{% component (phil_courses_kit('Button')) %}`.
     */
    #[AsTwigFunction(name: 'phil_courses_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
