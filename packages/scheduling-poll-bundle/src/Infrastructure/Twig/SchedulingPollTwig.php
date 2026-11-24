<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Infrastructure\Twig;

use DateTimeZone;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\Exception\NotFound;
use Phil\SchedulingPollBundle\Application\Exception\NotOrganizer;
use Phil\SchedulingPollBundle\Application\SchedulingPollViews;
use Phil\SchedulingPollBundle\Application\View\ResultsView;
use Twig\Attribute\AsTwigFunction;

/**
 * The functions of the templates: the answers of a poll for its organizer, which `@PhilSchedulingPoll/results.html.twig`
 * draws; the time zones a respondent may pick; and the name under which a recipe of the kit is installed, the
 * application's components directory holding the recipes in the subdirectory of its `ux_toolkit` configuration: with
 * `ui`, `Table:Row` is `ui:Table:Row`.
 */
final readonly class SchedulingPollTwig
{
    private string $prefix;

    public function __construct(
        private SchedulingPollViews $views,
        string $kitSubdirectory = '',
    ) {
        $path = trim($kitSubdirectory, '/');
        $this->prefix = '' === $path ? '' : str_replace('/', ':', $path).':';
    }

    /**
     * @throws NotFound
     * @throws NotOrganizer
     */
    #[AsTwigFunction(name: 'phil_scheduling_poll_results')]
    public function results(string $poll, ResourceReference|string $organizer, ?string $timezone = null): ResultsView
    {
        return $this->views->results($poll, \is_string($organizer) ? ResourceReference::fromString($organizer) : $organizer, $timezone);
    }

    /**
     * @return list<string> the time zones of the IANA database, in their order
     */
    #[AsTwigFunction(name: 'phil_scheduling_poll_timezones')]
    public function timezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * The name of a recipe of the kit, `Table:Row`, as the application installed it.
     */
    #[AsTwigFunction(name: 'phil_scheduling_poll_kit')]
    public function kit(string $recipe): string
    {
        return $this->prefix.$recipe;
    }
}
