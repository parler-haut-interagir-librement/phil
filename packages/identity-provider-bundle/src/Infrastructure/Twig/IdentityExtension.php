<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Twig;

use Phil\IdentityProviderBundle\Application\Modules;
use Twig\Attribute\AsTwigFunction;

/**
 * The functions of the bundle's templates:
 *
 * - `phil_identity_kit()`, the name under which a recipe of the kit is installed:
 *   `{% component (phil_identity_kit('Button')) with {type: 'submit'} %}`;
 * - `phil_identity_module()`, whether a module is on, so that a page links only to what exists.
 */
final readonly class IdentityExtension
{
    public function __construct(
        private KitComponents $kit,
        private Modules $modules,
    ) {
    }

    #[AsTwigFunction(name: 'phil_identity_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[AsTwigFunction(name: 'phil_identity_module')]
    public function module(string $module): bool
    {
        return $this->modules->has($module);
    }
}
