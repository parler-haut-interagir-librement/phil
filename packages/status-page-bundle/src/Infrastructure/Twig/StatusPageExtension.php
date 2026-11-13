<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Twig;

use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Twig\Attribute\AsTwigFunction;

/**
 * What the templates of the page use, declared by attributes and run as a Twig runtime:
 *
 * - `phil_status_kit()`, the name under which a recipe of the kit is installed;
 * - `phil_status_variant()`, the variant of the Badge recipe a state is drawn with — the state's name is always
 *   written beside it, so colour is never the only sign.
 */
final readonly class StatusPageExtension
{
    public function __construct(
        private KitComponents $kit = new KitComponents(),
    ) {
    }

    #[AsTwigFunction(name: 'phil_status_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[AsTwigFunction(name: 'phil_status_variant')]
    public function variant(ComponentStatus $status): string
    {
        return match ($status) {
            ComponentStatus::Operational => 'outline',
            ComponentStatus::Maintenance, ComponentStatus::Degraded => 'secondary',
            ComponentStatus::PartialOutage, ComponentStatus::MajorOutage => 'destructive',
        };
    }
}
