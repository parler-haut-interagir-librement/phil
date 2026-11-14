<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Twig;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The guided tour of the current user in a flow, `<twig:PhilOnboarding:Tour flow="author" />`: the steps for the
 * Stimulus controller, which highlights each target in turn. It starts at the first pending step when the page loads,
 * unless `autostart` is false, and then on the window event `phil-onboarding:start`. Nothing for a visitor, a flow
 * finished or dismissed, or a user who turned the tours off.
 *
 * @phpstan-import-type ViewData from OnboardingView
 */
#[AsTwigComponent(name: 'PhilOnboarding:Tour', template: '@PhilOnboarding/components/Tour.html.twig', exposePublicProps: false)]
final class TourComponent
{
    public string $flow = '';

    public bool $autostart = true;

    public function __construct(
        private readonly OnboardingView $view,
    ) {
    }

    /**
     * @return ViewData|null
     */
    public function getData(): ?array
    {
        return $this->view->tour($this->flow);
    }
}
