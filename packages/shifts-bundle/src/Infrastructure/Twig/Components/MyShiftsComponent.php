<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Twig\Components;

use Phil\Shifts\Hours\ServedHours;
use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Application\Contract\CurrentPerson;
use Phil\ShiftsBundle\Application\Rosters;
use Psr\Clock\ClockInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The shifts to come of the person signed in, and the minutes they served, `<twig:PhilShifts:MyShifts limit="5" />`:
 * what a dashboard of a volunteer shows. Nobody signed in renders nothing.
 */
#[AsTwigComponent(name: 'PhilShifts:MyShifts', template: '@PhilShifts/components/MyShifts.html.twig', exposePublicProps: false)]
final class MyShiftsComponent
{
    /** The shifts shown, 1 to 50. */
    public int $limit = 5;

    public function __construct(
        private readonly Rosters $rosters,
        private readonly CurrentPerson $person,
        private readonly ClockInterface $clock,
        private readonly string $timeZone,
    ) {
    }

    public function getPerson(): ?string
    {
        return $this->person->current();
    }

    /**
     * @return list<Roster> the shifts not over yet, in the order they start
     *
     * @phpstan-impure
     */
    public function getUpcoming(): array
    {
        $person = $this->person->current();

        if (null === $person) {
            return [];
        }

        $now = $this->clock->now();
        $upcoming = array_filter($this->rosters->ofVolunteer($person), static fn (Roster $roster): bool => !$roster->shift->isOverAt($now));

        return \array_slice($upcoming, 0, max(1, min($this->limit, 50)));
    }

    /**
     * @phpstan-impure
     */
    public function getServed(): ?ServedHours
    {
        $person = $this->person->current();

        return null === $person ? null : $this->rosters->served($person);
    }

    public function getZone(): string
    {
        return $this->timeZone;
    }
}
