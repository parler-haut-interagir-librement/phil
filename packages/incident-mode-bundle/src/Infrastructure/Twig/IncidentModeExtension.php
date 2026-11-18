<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Infrastructure\Twig;

use DateTimeImmutable;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Preset;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\Suspension;
use Phil\IncidentModeBundle\Infrastructure\Translation\IncidentWording;
use Twig\Attribute\AsTwigFunction;

/**
 * What the templates of a site use of the incident mode, declared by attributes and run as a Twig runtime:
 *
 * - `phil_incident_suspended('signups')`, whether the restriction applies to the member of the request now;
 * - `phil_incident_suspensions()`, every restriction that applies to them now, with its end, for a banner;
 * - `phil_incident_sentence(suspension)`, what a suspension stops and until when, in the visitor's language;
 * - `phil_incident_moment(date)`, a moment in the visitor's language and the configured time zone, which it names;
 * - `phil_incident_preset(preset)`, `phil_incident_restriction(name)` and `phil_incident_duration(duration)`, their
 *   words;
 * - `phil_incident_kit('Button')`, the name under which a recipe of the kit is installed.
 */
final readonly class IncidentModeExtension
{
    public function __construct(
        private Checkpoint $checkpoint,
        private IncidentWording $wording,
        private KitComponents $kit = new KitComponents(),
    ) {
    }

    #[AsTwigFunction(name: 'phil_incident_suspended')]
    public function suspended(string $restriction): bool
    {
        return $this->checkpoint->applies($restriction);
    }

    /**
     * @return list<Suspension>
     */
    #[AsTwigFunction(name: 'phil_incident_suspensions')]
    public function suspensions(): array
    {
        return $this->checkpoint->suspensions();
    }

    #[AsTwigFunction(name: 'phil_incident_sentence')]
    public function sentence(Suspension $suspension): string
    {
        return $this->wording->sentence($suspension);
    }

    #[AsTwigFunction(name: 'phil_incident_moment')]
    public function moment(DateTimeImmutable $moment): string
    {
        return $this->wording->moment($moment);
    }

    #[AsTwigFunction(name: 'phil_incident_preset')]
    public function preset(Preset|string $preset): string
    {
        return $this->wording->preset($preset);
    }

    #[AsTwigFunction(name: 'phil_incident_restriction')]
    public function restriction(string $name): string
    {
        return $this->wording->restriction($name);
    }

    #[AsTwigFunction(name: 'phil_incident_duration')]
    public function duration(Duration $duration): string
    {
        return $this->wording->duration($duration);
    }

    /**
     * `ui:Button` for `Button` when the recipes are installed in the subdirectory `ui`; a template calls the recipe with
     * `{% component (phil_incident_kit('Button')) %}`.
     */
    #[AsTwigFunction(name: 'phil_incident_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
