<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeInterface;
use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\Repository\AttendanceRepository;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;

/**
 * A member's own calendar: the occurrences of a period they attend — an occurrence signed up for, or every one of an
 * event attended as a whole —, those of the events they host when the application names them as a host too, and those
 * of the sources they follow — hosts' calendars, tags, venues, events — less those they hide, in order, each once. What
 * a member attends or hosts is always there: hiding a source only takes away what following brought.
 *
 * The period is read once, and the hosts of its events in one query; its occurrences count against the request's
 * `ExpansionBudget`, as any of the calendar's.
 */
final readonly class PersonalCalendar
{
    public function __construct(
        private Calendar $calendar,
        private AttendanceRepository $attendances,
        private EventRepository $events,
        private ?CalendarChoices $choices = null,
    ) {
    }

    /**
     * @param bool $hosted with the occurrences of the events the member hosts, named by the same kind and reference
     *
     * @return list<Occurrence>
     */
    public function occurrences(Member $member, DateTimeInterface $from, DateTimeInterface $through, bool $hosted = true): array
    {
        $attended = $this->attendances->attendedBy($member);
        $choices = $this->choices?->of($member) ?? new MemberChoices();

        if ([] === $attended && !$hosted && $choices->isEmpty()) {
            return [];
        }

        $occurrences = $this->calendar->occurrences($from, $through);
        $hosts = $this->hosts($occurrences);
        $self = $member->toString();
        $found = [];

        foreach ($occurrences as $occurrence) {
            $event = $occurrence->event;
            $of = $hosts[$event->id] ?? [];
            $keys = $attended[$event->id] ?? [];

            $kept = \in_array(Attendance::EVERY_OCCURRENCE, $keys, true)
                || \in_array(Attendance::key($occurrence->start), $keys, true)
                || ($hosted && \in_array($self, $of, true))
                || (!$choices->hides($event, $of) && $choices->follows($event, $of));

            if ($kept) {
                $found[] = $occurrence;
            }
        }

        return $found;
    }

    /**
     * The hosts of the occurrences' events, `kind:reference`, by event: one query for the period.
     *
     * @param list<Occurrence> $occurrences
     *
     * @return array<string, list<string>>
     */
    private function hosts(array $occurrences): array
    {
        $events = [];

        foreach ($occurrences as $occurrence) {
            $events[$occurrence->event->id] = $occurrence->event;
        }

        if ([] === $events) {
            return [];
        }

        return array_map(
            static fn (array $of): array => array_map(static fn (EventHost $host): string => $host->kind.':'.$host->reference, $of),
            $this->events->hostsOfEach($events),
        );
    }
}
