<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Twig;

use Phil\SitesConsoleBundle\Domain\Status\DeliveryOutcome;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;
use Twig\Attribute\AsTwigFunction;

/**
 * The names of the recipes of the kit the templates call, and the variant of the Badge each state is drawn with.
 */
final readonly class SitesConsoleExtension
{
    public function __construct(
        private KitComponents $kit = new KitComponents(),
    ) {
    }

    #[AsTwigFunction(name: 'phil_sites_console_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[AsTwigFunction(name: 'phil_sites_console_variant')]
    public function variant(HealthState|StepOutcome|DeliveryOutcome|PlanState $state): string
    {
        return match ($state) {
            HealthState::Up, StepOutcome::Succeeded, DeliveryOutcome::Applied, DeliveryOutcome::Replayed, PlanState::Completed => 'outline',
            HealthState::Down, StepOutcome::Failed, DeliveryOutcome::Failed, PlanState::Stopped => 'destructive',
            default => 'secondary',
        };
    }
}
