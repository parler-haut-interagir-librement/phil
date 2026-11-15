<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * What the templates of the bundle use, declared by attributes and run as a Twig runtime: `phil_terms_kit()`, the name
 * under which a recipe of the kit is installed.
 */
final readonly class TermsTwig
{
    private KitComponents $kit;

    public function __construct(?KitComponents $kit = null)
    {
        $this->kit = $kit ?? new KitComponents();
    }

    /**
     * `ui:Field:Label` for `Field:Label` when the recipes are installed in the subdirectory `ui`; a template calls the
     * recipe with `{% component (phil_terms_kit('Field:Label')) %}`.
     */
    #[AsTwigFunction(name: 'phil_terms_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
