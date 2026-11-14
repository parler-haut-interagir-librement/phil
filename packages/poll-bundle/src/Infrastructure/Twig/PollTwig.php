<?php

declare(strict_types=1);

namespace Phil\PollBundle\Infrastructure\Twig;

use Phil\PollBundle\Application\Exception\NotFound;
use Phil\PollBundle\Application\PollViews;
use Phil\PollBundle\Application\View\BallotView;
use Phil\PollBundle\Application\View\ResultsView;
use Phil\Resource\ResourceReference;
use Twig\Attribute\AsTwigFunction;

/**
 * The views of a poll in a template, for the viewer the template names — a reference of `phil/resource`, as text or
 * as an object, or `null` for someone not signed in —, which `@PhilPoll/ballot.html.twig` and
 * `@PhilPoll/results.html.twig` draw; and the name under which a recipe of the kit is installed, the application's
 * components directory holding the recipes in the subdirectory of its `ux_toolkit` configuration: with `ui`,
 * `Field:Set` is `ui:Field:Set`.
 */
final readonly class PollTwig
{
    private string $prefix;

    public function __construct(
        private PollViews $views,
        string $kitSubdirectory = '',
    ) {
        $path = trim($kitSubdirectory, '/');
        $this->prefix = '' === $path ? '' : str_replace('/', ':', $path).':';
    }

    /**
     * @throws NotFound
     */
    #[AsTwigFunction(name: 'phil_poll_ballot')]
    public function ballot(string $poll, ResourceReference|string|null $viewer = null): BallotView
    {
        return $this->views->ballot($poll, $this->viewer($viewer));
    }

    /**
     * @throws NotFound
     */
    #[AsTwigFunction(name: 'phil_poll_results')]
    public function results(string $poll, ResourceReference|string|null $viewer = null): ?ResultsView
    {
        return $this->views->results($poll, $this->viewer($viewer));
    }

    /**
     * The name of a recipe of the kit, `Field:Set`, as the application installed it.
     */
    #[AsTwigFunction(name: 'phil_poll_kit')]
    public function kit(string $recipe): string
    {
        return $this->prefix.$recipe;
    }

    private function viewer(ResourceReference|string|null $viewer): ?ResourceReference
    {
        return \is_string($viewer) ? ResourceReference::fromString($viewer) : $viewer;
    }
}
