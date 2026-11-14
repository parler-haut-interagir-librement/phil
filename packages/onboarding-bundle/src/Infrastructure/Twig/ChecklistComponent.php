<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Twig;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The checklist of the current user in a flow, `<twig:PhilOnboarding:Checklist flow="author" />`: the steps in order
 * with their state, the share done, and forms to tick, skip, dismiss and start again, which work without JavaScript.
 * Nothing for a visitor or a dismissed flow.
 *
 * @phpstan-import-type ViewData from OnboardingView
 */
#[AsTwigComponent(name: 'PhilOnboarding:Checklist', template: '@PhilOnboarding/components/Checklist.html.twig', exposePublicProps: false)]
final class ChecklistComponent
{
    public string $flow = '';

    public function __construct(
        private readonly OnboardingView $view,
    ) {
    }

    /**
     * @return ViewData|null
     */
    public function getData(): ?array
    {
        return $this->view->checklist($this->flow);
    }
}
