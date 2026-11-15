<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * `phil_lti_kit()`, the name under which a recipe of the kit is installed, for the templates of the bundle:
 * `{% component (phil_lti_kit('Button')) %}`.
 */
final readonly class LtiExtension
{
    private KitComponents $kit;

    public function __construct(?KitComponents $kit = null)
    {
        $this->kit = $kit ?? new KitComponents();
    }

    #[AsTwigFunction(name: 'phil_lti_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
