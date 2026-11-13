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
 * `@PhilPoll/results.html.twig` draw.
 */
final readonly class PollTwig
{
    public function __construct(
        private PollViews $views,
    ) {
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

    private function viewer(ResourceReference|string|null $viewer): ?ResourceReference
    {
        return \is_string($viewer) ? ResourceReference::fromString($viewer) : $viewer;
    }
}
