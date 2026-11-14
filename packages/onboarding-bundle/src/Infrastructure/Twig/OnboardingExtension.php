<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Twig;

use Phil\OnboardingBundle\Application\FlowProgress;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * The checklist and the tour in any template, without writing a component tag:
 *
 * ```twig
 * {{ onboarding_checklist('author') }}
 * {{ onboarding_tour('author') }}
 * {% set progress = onboarding_progress('author') %}
 * ```
 *
 * and `phil_onboarding_kit()`, the name under which a recipe of the kit is installed, for the templates of the bundle.
 */
final readonly class OnboardingExtension
{
    private KitComponents $kit;

    public function __construct(
        private OnboardingView $view,
        ?KitComponents $kit = null,
    ) {
        $this->kit = $kit ?? new KitComponents();
    }

    /**
     * `ui:Button` for `Button` when the recipes are installed in the subdirectory `ui`; a template calls the recipe with
     * `{% component (phil_onboarding_kit('Button')) %}`.
     */
    #[AsTwigFunction(name: 'phil_onboarding_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[AsTwigFunction(name: 'onboarding_progress')]
    public function progress(string $flow): ?FlowProgress
    {
        return $this->view->progress($flow);
    }

    #[AsTwigFunction(name: 'onboarding_checklist', needsEnvironment: true, isSafe: ['html'])]
    public function checklist(Environment $twig, string $flow): string
    {
        $data = $this->view->checklist($flow);

        return null === $data ? '' : $twig->render('@PhilOnboarding/_checklist.html.twig', ['data' => $data, 'attributes' => null]);
    }

    #[AsTwigFunction(name: 'onboarding_tour', needsEnvironment: true, isSafe: ['html'])]
    public function tour(Environment $twig, string $flow, bool $autostart = true): string
    {
        $data = $this->view->tour($flow);

        return null === $data ? '' : $twig->render('@PhilOnboarding/_tour.html.twig', ['data' => $data, 'autostart' => $autostart, 'attributes' => null]);
    }
}
